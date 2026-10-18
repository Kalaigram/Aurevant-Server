<?php

declare(strict_types=1);

namespace App\Application\Employee\DTOs;

use App\Application\Access\DTOs\AssignmentDTO;
use App\Application\Auth\DTOs\UserDTO;

final readonly class CreatedEmployee
{
    public function __construct(
        public UserDTO $user,
        public string $initialPassword,
        public AssignmentDTO $assignment,
    ) {}
}
