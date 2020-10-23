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
        $realm = $session->getRealm()->getRealmName();

        /**
         * Admin can publish, call, subscribe, ...
         */
        if ($username === 'admin') {
            return true;
        }

        /*if ($actionMsg instanceof \Thruway\Message\PublishMessage) {
            return true; //?
        } else */if ($actionMsg instanceof \Thruway\Message\SubscribeMessage) {
            $channel = $actionMsg->getTopicName();
            $metaInfo = $session->getMetaInfo();
            error_log("subscribing to " . $channel . ' realm ' . $realm);
            error_log(json_encode($metaInfo));
            return parent::isAuthorizedTo($session, $actionMsg);

            /**
             * Allowed by realm role.
             */
            $configRoles = config('pckg.websocket.auth.realms.' . $realm . '.roles', []);
            foreach ($configRoles as $role) {
                if (!$role['allow'] || $role['action'] !== 'subscribe' || $role['role'] !== $metaInfo['authrole']) {
                    continue;
                }

                /**
                 * Allowed by uri, role and realm.
                 */
                if ($role['uri'] === $channel) {
                    return true;
                } else if (substr($role['uri'], -1) === '.' && preg_match('/^' . $role['uri'] . '.(.*)$/i', $channel)) {
                    return true;
                }
            }

            /**
             * Get authenticated user.
             */
            $user = null;
            if ($username === 'guest') {
                $user = null;
            } else {
                /**
                 * Isn't this solved in UserDb?
                 */
                $exploded = explode(':', $username, 2);
                if (count($exploded) === 2) {
                    $user = User::gets(['id' => $exploded[0], 'email' => $exploded[1]]);
                    if (!$user) {
                        error_log('no user');
                        return false;
                    }
                } else if ($realm !== 'realm2') {
                    error_log('not double parameter');
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
                if (!preg_match('/^' . $gate['channel'] . '$/i', $channel)) {
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