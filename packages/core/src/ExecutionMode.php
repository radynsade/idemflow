<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

enum ExecutionMode: string {
	case Atomic = 'atomic';
}
