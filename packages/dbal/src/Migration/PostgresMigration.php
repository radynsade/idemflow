<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Migration;

use Doctrine\DBAL\Connection;
use Override;

final class PostgresMigration implements MigrationInterface {
	public function __construct(
		private readonly Connection $connection,
		private readonly string $tableName,
	) {
	}

	#[Override]
	public function up(): void {
		$tableName = $this->tableName;

		$sql = <<<SQL
		create table {$tableName} (
			"scope" varchar(120) not null,
			"key" character(64) not null,
			"fingerprint" character(64) not null,
			"status" varchar(20) not null,
			"owner_id" text not null,
			"attempt" bigint not null,
			"started_at" timestamp(6) with time zone not null,
			"result_payload" jsonb,
			"result_codec" varchar(100),
			"result_version" integer,
			"result_type" text,
			"result_metadata" jsonb,
			"completed_at" timestamp(6) with time zone,
			"expires_at" timestamp(6) with time zone,
			primary key ("scope", "key"),
			check (length("scope") > 0),
			check ("key" ~ '^[0-9a-f]{64}$'),
			check ("fingerprint" ~ '^[0-9a-f]{64}$'),
			check (length("owner_id") > 0),
			check ("attempt" > 0),
			check ("result_version" is null or "result_version" > 0),
			check ("status" in ('processing', 'completed', 'failed', 'ambiguous', 'expired')),
			check (
				(
					"result_payload" is null
					and "result_codec" is null
					and "result_version" is null
					and "result_type" is null
					and "result_metadata" is null
				)
				or
				(
					"result_payload" is not null
					and "result_codec" is not null
					and "result_version" is not null
					and "result_type" is not null
					and "result_metadata" is not null
				)
			),
			check (
				"status" <> 'processing'
				or (
					"result_payload" is null
					and "completed_at" is null
					and "expires_at" is null
				)
			),
			check (
				"status" <> 'completed'
				or (
					"result_payload" is not null
					and "completed_at" is not null
					and "expires_at" is not null
				)
			),
			check (
				"completed_at" is null
				or "expires_at" is null
				or "expires_at" > "completed_at"
			)
		)
		SQL;

		$this->connection->executeStatement($sql);
	}

	#[Override]
	public function down(): void {
		$tableName = $this->tableName;
		$this->connection->executeStatement("drop table {$tableName}");
	}
}
