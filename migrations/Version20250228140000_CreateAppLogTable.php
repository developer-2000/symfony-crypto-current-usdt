<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250228140000_CreateAppLogTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_log table for LogManager DB handler.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('app_log');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('channel', 'string', ['length' => 255]);
        $table->addColumn('level', 'string', ['length' => 50]);
        $table->addColumn('message', 'text');
        $table->addColumn('context', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'string', ['length' => 20]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['created_at'], 'idx_app_log_created_at');
        $table->addIndex(['channel'], 'idx_app_log_channel');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('app_log');
    }
}
