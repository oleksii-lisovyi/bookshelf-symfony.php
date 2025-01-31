<?php

declare(strict_types=1);

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

readonly class BookUpdateDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,

        public ?string $shortDescription,

        #[Assert\Date]
        public ?\DateTimeInterface $publishedAt
    ) {
    }
}
