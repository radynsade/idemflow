<?php

/**
 * @author Nikita Prokopenko <radynje@gmail.com>
 */

declare(strict_types = 1);

namespace IdemFlow\Core;

enum OperationStatus: string {
	case Processing = 'processing';

	case Completed = 'completed';

	case Failed = 'failed';

	case Ambiguous = 'ambiguous';

	case Expired = 'expired';
}
