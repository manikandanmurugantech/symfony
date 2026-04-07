<?php

declare(strict_types=1);

namespace App\UI\Console;

use App\Dashboard\Application\Command\CreateSiteRecord\CreateSiteRecordCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'dashboard:seed',
    description: 'Seeds the dashboard with 100,000+ site records for performance testing.',
)]
final class SeedDashboardCommand extends Command
{
    private const BATCH_SIZE    = 1000;
    private const DEFAULT_COUNT = 100000;

    private const URLS = [
        '/home', '/about', '/products', '/contact', '/blog',
        '/api/users', '/api/orders', '/api/products', '/checkout',
        '/search', '/login', '/register', '/dashboard', '/profile',
        '/settings', '/help', '/faq', '/pricing', '/docs', '/status',
    ];

    private const COUNTRIES = [
        'IN', 'US', 'GB', 'DE', 'FR', 'JP', 'AU', 'CA', 'BR', 'SG',
        'AE', 'NL', 'SE', 'NO', 'IT', 'ES', 'KR', 'MX', 'ZA', 'NG',
    ];

    private const HTTP_METHODS  = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    private const STATUS_CODES  = [200, 200, 200, 200, 201, 301, 302, 400, 401, 403, 404, 500];
    private const USER_AGENTS   = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36',
        'PostmanRuntime/7.36.0',
        'curl/8.4.0',
    ];

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('count', 'c', InputOption::VALUE_OPTIONAL,
            'Number of records to seed', self::DEFAULT_COUNT);
        $this->addOption('truncate', 't', InputOption::VALUE_NONE,
            'Truncate existing data before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');

        $io->title('Dashboard Seeder');
        $io->text(sprintf('Seeding %s records in batches of %s...', number_format($count), number_format(self::BATCH_SIZE)));

        if ($input->getOption('truncate')) {
            $this->connection->executeStatement('TRUNCATE TABLE dashboard_read_model');
            $this->connection->executeStatement('TRUNCATE TABLE site_records');
            $io->warning('Existing data truncated.');
        }

        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %estimated:-6s%');
        $progressBar->start();

        $inserted = 0;
        $start    = microtime(true);

        while ($inserted < $count) {
            $batchCount = min(self::BATCH_SIZE, $count - $inserted);
            $this->insertBatch($batchCount);
            $inserted    += $batchCount;
            $progressBar->advance($batchCount);
        }

        $progressBar->finish();
        $elapsed = round(microtime(true) - $start, 2);

        $io->newLine(2);
        $io->success(sprintf(
            'Seeded %s records in %ss (%.0f records/sec)',
            number_format($inserted),
            $elapsed,
            $inserted / max(1, $elapsed)
        ));

        return Command::SUCCESS;
    }

    private function insertBatch(int $count): void
    {
        $now    = new \DateTimeImmutable();
        $values = [];
        $params = [];

        for ($i = 0; $i < $count; $i++) {
            $id          = $this->generateUuid();
            $occurredAt  = $now->modify(sprintf('-%d seconds', random_int(0, 31536000)));

            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
            array_push(
                $params,
                $id,
                self::URLS[array_rand(self::URLS)],
                $this->randomIp(),
                self::HTTP_METHODS[array_rand(self::HTTP_METHODS)],
                self::STATUS_CODES[array_rand(self::STATUS_CODES)],
                round(mt_rand(10, 3000) / 10, 1),
                self::COUNTRIES[array_rand(self::COUNTRIES)],
                $occurredAt->format('Y-m-d H:i:s'),
                $id // also insert into read model
            );

            // Batch insert into BOTH write model and read model simultaneously
        }

        // Insert into write model (site_records)
        $this->batchInsertSiteRecords($count, $now);

        // Insert directly into read model (skip event bus for bulk seed performance)
        $this->batchInsertReadModel($count, $now);
    }

    private function batchInsertSiteRecords(int $count, \DateTimeImmutable $now): void
    {
        $values = [];
        $params = [];

        for ($i = 0; $i < $count; $i++) {
            $id         = $this->generateUuid();
            $occurredAt = $now->modify(sprintf('-%d seconds', random_int(0, 31536000)));

            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($params,
                $id,
                self::URLS[array_rand(self::URLS)],
                $this->randomIp(),
                self::USER_AGENTS[array_rand(self::USER_AGENTS)],
                self::HTTP_METHODS[array_rand(self::HTTP_METHODS)],
                self::STATUS_CODES[array_rand(self::STATUS_CODES)],
                round(mt_rand(10, 3000) / 10, 1),
                self::COUNTRIES[array_rand(self::COUNTRIES)],
                $occurredAt->format('Y-m-d H:i:s'),
            );
        }

        $this->connection->executeStatement(
            'INSERT INTO site_records (id, url, ip_address, user_agent, http_method, status_code, response_time_ms, country, occurred_at)
             VALUES ' . implode(', ', $values),
            $params
        );
    }

    private function batchInsertReadModel(int $count, \DateTimeImmutable $now): void
    {
        $values = [];
        $params = [];

        for ($i = 0; $i < $count; $i++) {
            $id         = $this->generateUuid();
            $occurredAt = $now->modify(sprintf('-%d seconds', random_int(0, 31536000)));

            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($params,
                $id,
                self::URLS[array_rand(self::URLS)],
                $this->randomIp(),
                self::HTTP_METHODS[array_rand(self::HTTP_METHODS)],
                self::STATUS_CODES[array_rand(self::STATUS_CODES)],
                round(mt_rand(10, 3000) / 10, 1),
                self::COUNTRIES[array_rand(self::COUNTRIES)],
                $occurredAt->format('Y-m-d H:i:s'),
            );
        }

        $this->connection->executeStatement(
            'INSERT INTO dashboard_read_model (id, url, ip_address, http_method, status_code, response_time_ms, country, occurred_at)
             VALUES ' . implode(', ', $values) . '
             ON CONFLICT (id) DO NOTHING',
            $params
        );
    }

    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function randomIp(): string
    {
        return implode('.', [mt_rand(1, 254), mt_rand(0, 255), mt_rand(0, 255), mt_rand(1, 254)]);
    }
}
