<?php

namespace AppBundle\Security;

use Exception;
use AppBundle\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Encoder\BasePasswordEncoder;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class CompanyXSuperUserCookieEncoder.
 */
class CompanyXSuperUserCookieEncoder extends BasePasswordEncoder
{
	/** @var Request */
	private $request;

	/** @var array */
	private $keyGenerator;

	/** @var string */
	private $raw_decrypted;

	/** @var array */
	private $settings;

	/** @var int */
	private $keyLength;

	/** @var string */
	private $keyWord;

	/** @var string */
	private $keyGeneratorString;

	/** @var string */
	private $remoteAddr;

	/** @var string */
	private $sign;

	/**
	 * CompanyXSuperUserCookieEncoder constructor.
	 *
	 * @param int    $keyLength
	 * @param string $keyWord
	 * @param $keyGeneratorString
	 * @param $sign
	 * @param RequestStack        $requestStack
	 * @param SerializerInterface $serializer
	 */
	public function __construct($keyLength, $keyWord, $keyGeneratorString, $sign, RequestStack $requestStack, SerializerInterface $serializer)
	{
		if (!$keyLength || !$keyWord || !$keyGeneratorString || !$sign) {
			throw new BadCredentialsException('Missing required parameters for '.get_class());
		}

		$this->request = $requestStack->getCurrentRequest();
		if (!is_null($this->request)) {
			$postData = $serializer->decode($this->request->getContent(), Constants::JSON_SERIALIZATON_FORMAT);
			$this->remoteAddr = (array_key_exists(Constants::QUERY_PARAM_REMOTE_ADDR, $postData))
				? $postData[Constants::QUERY_PARAM_REMOTE_ADDR]
				: $this->request->server->get('REMOTE_ADDR');
		} else {
			$this->remoteAddr = null;
		}

		$this->keyLength = $keyLength;
		$this->keyWord = $keyWord;
		$this->keyGeneratorString = $keyGeneratorString;
		$this->sign = $sign;
		$this->settings = array('length' => $this->keyLength, 'word' => $this->keyWord);

		$this->keyGenerator = function () {
			// FirePHP changes the User-Agent header. Counter this.
			$userAgent = preg_replace(
				'~\s*FirePHP/[^\s]+~',
				'',
				$this->request->headers->get('User-Agent')
			);
			$ip_address = $this->remoteAddr;

			$keyGenerator = array(
				$ip_address,
				$userAgent,
				date('Y-m-d', time() - 7200),
				md5($this->keyGeneratorString.date('d')),
			);

			return $keyGenerator;
		};
	}

	/**
	 * Required method for PasswordEncoderInterface.
	 *
	 * @param string $raw
	 * @param string $salt
	 *
	 * @return string
	 */
	public function encodePassword($raw, $salt = null)
	{
		if ($this->isPasswordTooLong($raw)) {
			throw new BadCredentialsException('Invalid password.');
		}

		return $this->encrypt($raw);
	}

	/**
	 * Required method for PasswordEncoderInterface.
	 *
	 * @param string $cookie
	 * @param string $raw
	 * @param string $salt
	 *
	 * @return bool
	 */
	public function isPasswordValid($cookie, $raw = null, $salt = null)
	{
		try {
			$decryptedCookie = $this->decrypt($cookie);

			$match = '';
			foreach (str_split($this->settings['word']) as $c) {
				$match .= preg_quote($c).'.*';
			}
			$match = "~$match~i";

			//\\pr ( (strlen($d) === $settings['length']).' && '.(1 === preg_match($match,$d)).' && '.(strpos($d, date('j')) !== false))

			if (strlen($decryptedCookie) === $this->settings['length'] && 1 === preg_match($match, $decryptedCookie) && false !== strpos($decryptedCookie, date('j'))) {
				return true;
			}
		} catch (Exception $e) {
			return false;
		}

		return false;
	}

	/**
	 * @param $function
	 */
	public function setKeyGenerator($function)
	{
		$this->keyGenerator = $function;
	}

	/**
	 * @param null $key
	 *
	 * @return string
	 */
	public function getKey($key = null)
	{
		return json_encode(call_user_func_array($this->keyGenerator, array($key)));
	}

	/**
	 * @param $string
	 *
	 * @return string
	 */
	public function sign($string)
	{
		for ($i = 0, $max = min(8, strlen($string)); $i < $max; ++$i) {
			//pr ("DO `$string` $i");
			$string = substr($string, 0, ($i * 2) + 1).
				$this->sign[$i].
				substr($string, ($i * 2) + 1);
		}

		return $string;
	}

	/**
	 * @param $string
	 *
	 * @return bool|string
	 */
	public function unsign($string)
	{
		$tail = substr($string, 16);
		$string = substr($string, 0, 16);

		//pr ("$string -> $tail");
		$head = '';

		for ($i = 0, $max = min(16, strlen($string)); $i < $max; ++$i) {
			if (1 == ($i % 2)) {
				//pr ("COMPARE $i " . $string{$i} . " !== " . $sign{floor($i/2)});
				if ($string[$i] !== $this->sign[intval(floor($i / 2))]) {
					//pr ("FAIL");
					return false;
				}
			} else {
				$head .= $string[$i];
				//pr ("BUILD `$head`");
			}
		}
		//pr ("RETURN $head -> $tail");
		return $head.$tail;
	}

	/**
	 * @param $string
	 *
	 * @return string
	 */
	public function encrypt($string)
	{
		$key = $this->getKey();

		$string = $this->sign($string);
		$encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $string, MCRYPT_MODE_CBC, md5(md5($key))));

		return $encrypted;
	}

	/**
	 * @param $encrypted
	 *
	 * @return bool|string
	 */
	public function decrypt($encrypted)
	{
		$key = $this->getKey();

		$decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($encrypted), MCRYPT_MODE_CBC, md5(md5($key))), "\0");

		$this->raw_decrypted = $decrypted;

		return $this->unsign($decrypted);
	}

	/**
	 * @param $data
	 *
	 * @return string
	 */
	public function encryptData($data)
	{
		return $this->encrypt(json_encode($data));
	}

	/**
	 * @param $data
	 *
	 * @return mixed
	 */
	public function decryptData($data)
	{
		return json_decode($this->decrypt($data), true);
	}
}
