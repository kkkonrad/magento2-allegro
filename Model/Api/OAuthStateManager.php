<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use Magento\Backend\Model\Auth\Session;

class OAuthStateManager
{
    private const SESSION_KEY = 'macopedia_allegro_oauth_state';
    private const LIFETIME_SECONDS = 600;

    /** @var Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function generate(): string
    {
        $state = bin2hex(random_bytes(32));
        $this->session->setData(self::SESSION_KEY, [
            'hash' => hash('sha256', $state),
            'created_at' => time(),
        ]);

        return $state;
    }

    public function validateAndConsume(string $state): bool
    {
        $stored = $this->session->getData(self::SESSION_KEY);
        $this->session->unsetData(self::SESSION_KEY);

        if (!is_array($stored) || empty($stored['hash']) || empty($stored['created_at'])) {
            return false;
        }

        if ((int)$stored['created_at'] < time() - self::LIFETIME_SECONDS) {
            return false;
        }

        return hash_equals((string)$stored['hash'], hash('sha256', $state));
    }
}
