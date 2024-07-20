<?php

namespace Rumput;

use Exception;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class Session
{
    private static Session $instance;

    private SymfonySession $symfonySession;

    public static function start(): Session
    {
        $sessionPath = Rumput::$storagePath . '/sessions';
        $handler = new NativeFileSessionHandler($sessionPath);
        $storage = new NativeSessionStorage([
            'name' => 'appsession',
        ], $handler);

        $session = new SymfonySession($storage);
        $session->start();

        Session::$instance = new self($session);
        return Session::$instance;
    }

    public static function i(): SymfonySession
    {
        if (Session::$instance === null) {
            throw new Exception('Session not defined');
        }

        return self::$instance->getSession();
    }

    private function __construct(SymfonySession $session)
    {
        $this->symfonySession = $session;
    }

    public function getSession(): SymfonySession
    {
        return $this->symfonySession;
    }
}
