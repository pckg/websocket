<?php namespace Pckg\Websocket\Auth;

/**
 * Class PckgAuthProviderClient
 * @package Pckg\Websocket\Auth
 */
class PckgAuthProviderClient extends \Thruway\Authentication\AbstractAuthProviderClient
{

    /**
     * @var SignatureInterface
     */
    private $signer;

    /**
     * @param SignatureInterface $signer The signature handler.
     */
    public function setSignatureHandler(SignatureInterface $signer)
    {
        $this->signer = $signer;
    }

    /**
     * {@inheritDoc}
     */
    public function processHello(array $args)
    {
        Logger::info($this, "processHello...");

        $challenge = [
            'challenge' => $this->signer->create(),
        ];

        return ["CHALLENGE", (object)[
            "challenge" => json_encode($challenge),
            "challenge_method" => $this->getMethodName()
        ]];
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return 'pckg';
    }

    /**
     * Process Authenticate message
     *
     * @param mixed $signature
     * @param mixed $extra
     * @return array
     */
    public function processAuthenticate($signature, $extra = null)
    {

        if ($signature == "letMeIn") {
            return ["SUCCESS"];
        } else {
            return ["FAILURE"];
        }

    }

}