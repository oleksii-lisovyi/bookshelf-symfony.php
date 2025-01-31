<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{Author, Book};
use App\Model\{BookDto, BookUpdateDto};
use App\Repository\{AuthorRepository, BookRepository};
use App\Service\EntityToArray;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\{MapQueryParameter, MapRequestPayload, MapUploadedFile};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: "/books", name: "books_", format: 'json')]
class BookController extends AbstractController
{
    const PAGINATION_LIMIT_DEFAULT = 5;

    public function __construct(private readonly EntityToArray $entityToArray)
    {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function index(
        #[MapRequestPayload (acceptFormat: 'json')] BookDto $bookDto,
        EntityManagerInterface                              $entityManager,
        ValidatorInterface                                  $validator,
        AuthorRepository                                    $authorRepository
    ): JsonResponse
    {
        // TODO: currently a new book is created every time.
        //  Probably book name + publishing date can be a unique constraint.
        $book = Book::fromDto($bookDto);

        $errors = $validator->validate($book);
        if (\count($errors) > 0) {
            return $this->json((string)$errors, 400);
        }

        $entityManager->persist($book);

        foreach ($bookDto->authors as $authorDto) {
            if ($authorDto->id) {
                $author = $authorRepository->find($authorDto->id);

                if (!$author) {
                    return $this->json('Author can not be found by ID ' . $authorDto->id, 400);
                }
            } else {
                $author = Author::fromDto($authorDto);

                $errors = $validator->validate($author);
                if (\count($errors) > 0) {
                    return $this->json((string)$errors, 400);
                }

                $entityManager->persist($author);
            }

            $book->addAuthor($author);
        }

        $entityManager->flush();

        return $this->json($this->entityToArray->bookToArray($book, true));
    }

    #[Route(path: '', name: 'all', methods: ['GET'])]
    public function all(
        BookRepository                                                                                          $repository,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, options: ['min_range' => 1, 'max_range' => 100])] int $limit = self::PAGINATION_LIMIT_DEFAULT,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, options: ['min_range' => 0])] int                     $offset = 0,
        #[MapQueryParameter(name: 'include_authors')] bool                                                      $includeAuthors = false,
    ): JsonResponse
    {
        $paginator = $repository->getPagination($limit, $offset);

        return $this->json(\array_map(
            fn(Book $b) => $this->entityToArray->bookToArray($b, $includeAuthors),
            (array)$paginator->getIterator()
        ));
    }

    #[Route(path: '/{id}', name: 'one', methods: ['GET'])]
    public function one(
        Book                                               $book,
        #[MapQueryParameter(name: 'include_authors')] bool $includeAuthors = false,
    ): JsonResponse
    {
        return $this->json($this->entityToArray->bookToArray($book, $includeAuthors));
    }

    #[Route(path: '/search/by_author', name: 'search_by_author', methods: ['GET'])]
    public function findByAuthor(
        #[MapQueryParameter] string $q, //TODO: add minimum search query length
        AuthorRepository            $authorRepository
    ): JsonResponse
    {
        $authors = $authorRepository->findByText($q);

        $books = [];

        foreach ($authors as $a) {
            foreach ($a->getBooks() as $ab) {
                $books[$ab->getId()] = $ab;
            }
        }

        return $this->json(\array_map(fn(Book $b) => $this->entityToArray->bookToArray($b, true), $books));
    }

    #[Route(path: '/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        Book                                                      $book,
        #[MapRequestPayload (acceptFormat: 'json')] BookUpdateDto $bookDto,
        EntityManagerInterface                                    $entityManager,
        ValidatorInterface                                        $validator
    ): JsonResponse
    {
        if ($bookDto->name !== null) {
            $book->setName($bookDto->name);
        }

        if ($bookDto->shortDescription !== null) {
            $book->setShortDescription($bookDto->shortDescription);
        }

        if ($bookDto->publishedAt !== null) {
            $book->setPublishedAt($bookDto->publishedAt);
        }

        $errors = $validator->validate($book);
        if (\count($errors) > 0) {
            return $this->json((string)$errors, 400);
        }

        $entityManager->persist($book);
        $entityManager->flush();

        return $this->json($this->entityToArray->bookToArray($book, true));
    }

    #[Route(path: '/image/{id}', name: 'image', methods: ['POST'])]
    public function uploadImage(
        Book                   $book,
        #[MapUploadedFile([
            new Assert\File(mimeTypes: ['image/png', 'image/jpeg']),
            new Assert\Image(maxSize: '2M', filenameMaxLength: 255),
        ], name: 'image')]
        UploadedFile           $file,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $book->setImageFile($file);

        $entityManager->persist($book);
        $entityManager->flush();

        return $this->json($this->entityToArray->bookToArray($book,));
    }
}
