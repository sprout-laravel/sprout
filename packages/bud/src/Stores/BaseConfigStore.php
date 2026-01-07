<?php
declare(strict_types=1);

namespace Sprout\Bud\Stores;

use Illuminate\Contracts\Encryption\Encrypter;
use Sprout\Bud\Contracts\ConfigStore;

abstract class BaseConfigStore implements ConfigStore
{
    private string $name;

    /**
     * @var \Illuminate\Contracts\Encryption\Encrypter
     */
    private Encrypter $encrypter;

    /**
     * @param string                                     $name
     * @param \Illuminate\Contracts\Encryption\Encrypter $encrypter
     */
    public function __construct(string $name, Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
        $this->name      = $name;
    }

    /**
     * Get the registered name of the config store
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the encrypter for the config store
     *
     * @return \Illuminate\Contracts\Encryption\Encrypter
     */
    public function getEncrypter(): Encrypter
    {
        return $this->encrypter;
    }

    /**
     * Encrypt a configuration array
     *
     * @param array<string, mixed> $config
     *
     * @return string
     *
     * @throws \JsonException
     */
    protected function encryptConfig(array $config): string
    {
        return $this->getEncrypter()->encrypt($config);
    }

    /**
     * Decrypt an encrypted configuration string
     *
     * @param string $encryptedConfig
     *
     * @return array<string, mixed>|null
     */
    protected function decryptConfig(string $encryptedConfig): ?array
    {
        $decryptedConfig = $this->getEncrypter()->decrypt($encryptedConfig);

        if (! is_array($decryptedConfig)) {
            return null;
        }

        /** @var array<string, mixed> $decryptedConfig */

        return $decryptedConfig;
    }
}
