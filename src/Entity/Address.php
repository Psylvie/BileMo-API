<?php

namespace App\Entity;

use App\Repository\AddressRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
class Address
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(
        max: 60,
        maxMessage: 'Le nom de la rue ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['user:read'])]
    private ?string $street = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'Veuillez renseigner une ville.')]
    #[Assert\Length(
        max: 60,
        maxMessage: 'Le nom de la ville ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['user:read'])]
    private ?string $city = null;

    #[ORM\Column(length: 15)]
    #[Assert\NotBlank(message: 'Veuillez renseigner un code postal.')]
    #[Assert\Length(
        max: 15,
        maxMessage: 'Le code postal ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['user:read'])]
    private ?string $postalCode = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'Veuillez renseigner un pays.')]
    #[Assert\Length(
        max: 60,
        maxMessage: 'Le nom du pays ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['user:read'])]
    private ?string $country = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'addresses')]
    #[Assert\NotNull(message: 'Une adresse doit être associée à une société.')]
    private ?Company $company = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): self
    {
        $this->street = trim($street);

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = trim($city);

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = trim($postalCode);

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = trim($country);

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function __toString(): string
    {
        $street = $this->street ?? '';
        $postalCode = $this->postalCode ?? '';
        $city = $this->city ?? '';
        $country = $this->country ?? '';

        return trim("$street, $postalCode $city, $country");
    }
}
