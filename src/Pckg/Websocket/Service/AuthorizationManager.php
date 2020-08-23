<?php namespace Pckg\Websocket\Service;

use Pckg\Auth\Record\User;

class AuthorizationManager extends \Thruway\Authentication\AuthorizationManager
{
    public function isAuthorizedTo(\Thruway\Session $session, \Thruway\Message\ActionMessageInterface $actionMsg)
    {
        /**
         * Check for valid client subscriptions.
         * Of client is not subscribed, he cannot receive events.
         */
        $username = $session->getAuthenticationDetails()->getAuthId();
        if ($username === 'admin') {
            return true;
        }
        if ($actionMsg instanceof \Thruway\Message\SubscribeMessage) {
            $channel = $actionMsg->getTopicName();
            error_log("subscribing to " . $channel);
            error_log(json_encode($session->getMetaInfo()));

            /**
             * Get authenticated user.
             */
            $user = null;
            if ($username === 'guest') {
                $user = null;
            } else {
                $exploded = explode(':', $username, 2);
                if (count($exploded) !== 2) {
                    return false;
                }
                $user = User::gets(['id' => $exploded[0], 'email' => $exploded[1]]);
                if (!$user) {
                    return false;
                }
            }

            /**
             * Admins have access to all channels.
             * Guests have access to ca.whitespark.listings_scannerator.searchGuest.[theirId]
             * Users have access to ca.whitespark.listings_scannerator.search.[theirId]
             * Can we utilize our auth() here?
             */
            $gates = config('pckg.websocket.auth.gates', []);
            $authorized = false;
            foreach ($gates as $gate) {
                if (!preg_match('/' . $channel . '/i', $channel)) {
                    continue;
                }

                if ($gate['keeper']($channel, $user)) {
                    return true;
                }
            }
        }

        return parent::isAuthorizedTo($session, $actionMsg);
    }
}