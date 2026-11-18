<?php

declare(strict_types=1);

namespace Parcel\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sign',
    description: 'Sign a bundle so an app can check it has not been changed'
)]
class SignCommand extends Command
{
    /**
     * Bound into every signature so a key used for bundles can't be used to
     * pass off a signature made for something else, or the other way round.
     */
    public const PURPOSE = 'nativephp.parcel-bundle.v1:';

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Path to the compiled bundle')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Where to write the signature (default: <bundle>.sig)')
            ->addOption('public-key-output', null, InputOption::VALUE_REQUIRED, 'Also write the base64 public key to this file')
            ->setHelp(
                "Writes a detached Ed25519 signature over the bundle's SHA-256 digest, base64 encoded.\n\n".
                'The key is read from the PARCEL_SIGNING_KEY environment variable, so it never shows up in a '.
                'process list: a base64 Ed25519 secret key (64 bytes) or seed (32 bytes). '.
                'The public key is printed so the build can give it to the app.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bundle = (string) $input->getArgument('bundle');

        if (! is_file($bundle)) {
            $io->error("Bundle not found: {$bundle}");

            return Command::FAILURE;
        }

        $secretKey = self::secretKey((string) getenv('PARCEL_SIGNING_KEY'));

        if ($secretKey === null) {
            $io->error('PARCEL_SIGNING_KEY must be a base64 Ed25519 secret key (64 bytes) or seed (32 bytes).');

            return Command::FAILURE;
        }

        $signature = sodium_crypto_sign_detached(self::message($bundle), $secretKey);
        $publicKey = sodium_crypto_sign_publickey_from_secretkey($secretKey);
        sodium_memzero($secretKey);

        $signaturePath = $input->getOption('output') ?: $bundle.'.sig';
        file_put_contents($signaturePath, base64_encode($signature));

        if ($publicKeyPath = $input->getOption('public-key-output')) {
            file_put_contents($publicKeyPath, base64_encode($publicKey));
        }

        $io->definitionList(
            ['Signature' => $signaturePath],
            ['Public key' => base64_encode($publicKey)],
        );

        return Command::SUCCESS;
    }

    /**
     * What gets signed: the purpose, then the bundle's raw SHA-256 digest.
     */
    public static function message(string $bundle): string
    {
        return self::PURPOSE.hash_file('sha256', $bundle, true);
    }

    private static function secretKey(string $encoded): ?string
    {
        $decoded = base64_decode(trim($encoded), true);

        if ($decoded === false) {
            return null;
        }

        return match (strlen($decoded)) {
            SODIUM_CRYPTO_SIGN_SECRETKEYBYTES => $decoded,
            SODIUM_CRYPTO_SIGN_SEEDBYTES => sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($decoded)),
            default => null,
        };
    }
}
