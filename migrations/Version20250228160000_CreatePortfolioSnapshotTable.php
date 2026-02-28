<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250228160000_CreatePortfolioSnapshotTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create portfolio_snapshot table for hourly portfolio valuation.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('portfolio_snapshot');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('calculated_at', 'datetime_immutable');
        $table->addColumn('amount_usdt', 'decimal', ['precision' => 20, 'scale' => 8]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['calculated_at'], 'uniq_portfolio_snapshot_calculated_at');
        $table->addIndex(['calculated_at'], 'idx_portfolio_snapshot_calculated_at');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('portfolio_snapshot');
    }
}
