<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }
    
    public function getPagination(int $limit, int $offset): Paginator
    {
        $query = $this->createQueryBuilder('b')
            ->orderBy('b.name')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery();

        return new Paginator($query);
    }
}
