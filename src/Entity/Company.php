<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company extends AbstractAccount
{
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez entrer le nom de la société')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom de la société doit comporter au moins {{ limit }} caractères',
        maxMessage: 'Le nom de la société doit comporter au maximum {{ limit }} caractères'
    )]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Veuillez entrer une URL valide')]
    private ?string $webSite = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères')]
    protected ?string $phone = null;

    /**
     * List of addresses associated with the company.
     *
     * @var Collection<int, Address>
     */
    #[ORM\OneToMany(targetEntity: Address::class,
        mappedBy: 'company',
        cascade: ['persist', 'remove'],
        orphanRemoval: true)]
    private Collection $addresses;

    /**
     * @var Collection<int, Users>
     */
    #[ORM\ManyToMany(targetEntity: Users::class, mappedBy: 'companies')]
    private Collection $users;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->roles[] = 'ROLE_COMPANY';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): self
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getWebSite(): ?string
    {
        return $this->webSite;
    }

    public function setWebSite(?string $webSite): self
    {
        $this->webSite = $webSite;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * @return Collection<int, Address>
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(Address $address): self
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
            $address->setCompany($this);
        }

        return $this;
    }

    public function removeAddress(Address $address): self
    {
        if ($this->addresses->removeElement($address)) {
            if ($address->getCompany() === $this) {
                $address->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Users>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(Users $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->addCompany($this);
        }

        return $this;
    }

    public function removeUser(Users $user): self
    {
        if ($this->users->removeElement($user)) {
            $user->removeCompany($this);
        }

        return $this;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }
    public function getRoles(): array
    {
        if (empty($this->roles)) {
            $this->roles[] = 'ROLE_COMPANY';
        }

        return $this->roles;
    }
}
