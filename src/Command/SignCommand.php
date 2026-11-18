<?php

declare(strict_types=1);

namespace Parcel\Command;

use RuntimeException;
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
     * First line of every signed message. Bumped if the message ever changes,
     * so an old signature can't be read as a new kind.
     */
    public const FORMAT = 'nativephp-parcel-bundle-v1';

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Path to the compiled bundle')
            ->addOption('project', null, InputOption::VALUE_REQUIRED, 'The Bifrost project the bundle belongs to')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Where to write the signature (default: <bundle>.sig)')
            ->addOption('public-key-output', null, InputOption::VALUE_REQUIRED, 'Also write {"<key id>": "<public key>"} to this file')
            ->setHelp(
                "Signs the bundle with ECDSA P-256, the scheme Bifrost uses for OTA releases.\n\n".
                "The signed message is the format, the project, the bundle's SHA-256 and its size, one per line.\n".
                "The signature is written as JSON: {\"key_id\": ..., \"signature\": <base64 DER>}.\n\n".
                "With PARCEL_SIGNING_URL and PARCEL_SIGNING_TOKEN set, Bifrost signs it: only the hash is sent,\n".
                "and the private key never leaves Bifrost. Otherwise PARCEL_SIGNING_KEY holds a PEM private key\n".
                '(or base64 of one) and PARCEL_SIGNING_KEY_ID names it (default "local").'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bundle = (string) $input->getArgument('bundle');
        $project = (string) $input->getOption('project');

        if (! is_file($bundle)) {
            $io->error("Bundle not found: {$bundle}");

            return Command::FAILURE;
        }

        if ($project === '') {
            $io->error('Pass --project: the signature binds the bundle to one project.');

            return Command::FAILURE;
        }

        $sha256 = (string) hash_file('sha256', $bundle);
        $size = (int) filesize($bundle);

        try {
            $signed = getenv('PARCEL_SIGNING_URL')
                ? $this->signRemotely($project, $sha256, $size)
                : $this->signLocally(self::message($project, $sha256, $size));
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        // Whoever signed it, the signature has to check out against the key
        // it came with before anything ships with it.
        if (! self::verify(self::message($project, $sha256, $size), $signed['signature'], $signed['public_key'])) {
            $io->error('The signature does not match the bundle.');

            return Command::FAILURE;
        }

        $signaturePath = $input->getOption('output') ?: $bundle.'.sig';
        file_put_contents($signaturePath, json_encode([
            'key_id' => $signed['key_id'],
            'signature' => $signed['signature'],
        ], JSON_UNESCAPED_SLASHES));

        if ($publicKeyPath = $input->getOption('public-key-output')) {
            file_put_contents($publicKeyPath, json_encode([$signed['key_id'] => $signed['public_key']], JSON_UNESCAPED_SLASHES));
        }

        $io->definitionList(
            ['Signature' => $signaturePath],
            ['Key' => $signed['key_id']],
        );

        return Command::SUCCESS;
    }

    /**
     * The exact text that gets signed, one field per line.
     */
    public static function message(string $project, string $sha256, int $size): string
    {
        return implode("\n", [self::FORMAT, $project, $sha256, (string) $size]);
    }

    /**
     * @param  string  $signature  base64 DER ECDSA signature
     * @param  string  $publicKey  base64 DER SubjectPublicKeyInfo
     */
    public static function verify(string $message, string $signature, string $publicKey): bool
    {
        $der = base64_decode($signature, true);
        $key = openssl_pkey_get_public(self::pem(base64_decode($publicKey, true) ?: '', 'PUBLIC KEY'));

        return $der !== false && $key !== false && openssl_verify($message, $der, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @return array{key_id: string, signature: string, public_key: string}
     */
    private function signLocally(string $message): array
    {
        $encoded = trim((string) getenv('PARCEL_SIGNING_KEY'));

        if ($encoded === '') {
            throw new RuntimeException('Set PARCEL_SIGNING_URL and PARCEL_SIGNING_TOKEN to sign with Bifrost, or PARCEL_SIGNING_KEY to sign with a local key.');
        }

        $pem = str_contains($encoded, '-----BEGIN') ? $encoded : (string) base64_decode($encoded, true);
        $key = openssl_pkey_get_private($pem);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if ($key === false || $details === false || ($details['ec']['curve_name'] ?? null) !== 'prime256v1') {
            throw new RuntimeException('PARCEL_SIGNING_KEY must be an ECDSA P-256 private key in PEM.');
        }

        if (! openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signing with PARCEL_SIGNING_KEY failed.');
        }

        return [
            'key_id' => getenv('PARCEL_SIGNING_KEY_ID') ?: 'local',
            'signature' => base64_encode($signature),
            'public_key' => self::der($details['key']),
        ];
    }

    /**
     * Ask Bifrost to sign. Only the project, hash and size are sent; Bifrost
     * builds the message itself from its own record of the build.
     *
     * @return array{key_id: string, signature: string, public_key: string}
     */
    private function signRemotely(string $project, string $sha256, int $size): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer '.getenv('PARCEL_SIGNING_TOKEN'),
            ]),
            'content' => json_encode(['format' => self::FORMAT, 'project' => $project, 'sha256' => $sha256, 'size' => $size]),
            'timeout' => 60,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents((string) getenv('PARCEL_SIGNING_URL'), false, $context);
        $status = (int) (explode(' ', $http_response_header[0] ?? '')[1] ?? 0);
        $response = is_string($body) ? json_decode($body, true) : null;

        if ($status !== 200 || ! is_array($response)) {
            throw new RuntimeException("The signing service refused the bundle (HTTP {$status}).");
        }

        foreach (['key_id', 'signature', 'public_key'] as $field) {
            if (! is_string($response[$field] ?? null) || $response[$field] === '') {
                throw new RuntimeException("The signing service's answer has no {$field}.");
            }
        }

        return ['key_id' => $response['key_id'], 'signature' => $response['signature'], 'public_key' => $response['public_key']];
    }

    private static function der(string $pem): string
    {
        return (string) preg_replace('/-----[^-]+-----|\s+/', '', $pem);
    }

    private static function pem(string $der, string $label): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$label}-----\n";
    }
}
