<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\DBAL\Migration;

interface MigrationInterface {
	public function up(): void;

	public function down(): void;
}
