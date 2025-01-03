<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Product
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez renseigner un nom pour le produit.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le nom du produit ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['product:read', 'product:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez renseigner une description pour le produit.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'La description du produit ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['product:read', 'product:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez renseigner un modèle pour le produit.')]
    #[Groups(['product:read', 'product:write'])]
    private ?string $model = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez renseigner une marque pour le produit.')]
    #[Groups(['product:read', 'product:write'])]
    private ?string $brand = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez renseigner une référence pour le produit.')]
    #[Groups(['product:read', 'product:write'])]
    private ?string $reference = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Veuillez renseigner un prix pour le produit.')]
    #[Assert\Positive(message: 'Le prix doit être un nombre positif.')]
    #[Groups(['product:read', 'product:write'])]
    private ?float $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'La dimension ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['product:read', 'product:write'])]
    private ?string $dimension = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Veuillez renseigner la quantité en stock.')]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    #[Groups(['product:read', 'product:write'])]
    private ?int $stock = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['product:read', 'product:write'])]
    private ?bool $isAvailable = true;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Veuillez renseigner une URL valide pour l’image.')]
    #[Groups(['product:read', 'product:write'])]
    private ?string $image = null;

    public function __construct()
    {
        $this->isAvailable = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = trim($model);

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): self
    {
        $this->brand = trim($brand);

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = trim($reference);

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getDimension(): ?string
    {
        return $this->dimension;
    }

    public function setDimension(?string $dimension): self
    {
        $this->dimension = trim($dimension);

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): self
    {
        $this->stock = $stock;

        return $this;
    }

    public function getIsAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): self
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name.' ('.$this->brand.') - '.number_format($this->price, 2).'€';
    }
}
