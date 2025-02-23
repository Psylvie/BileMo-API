<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[Hateoas\Relation(
    name: 'user_delete',
    href: "expr(object.getCompanies() && object.getCompanies().count() > 0 ? '/api/companies/' ~ object.getCompanies().first().getId() ~ '/users/' ~ object.getId() : '')",
    exclusion: new Hateoas\Exclusion(groups: ['user:read'])
)]
#[Hateoas\Relation(
    name: 'user_detail',
    href: "expr(object.getCompanies().count() > 0 ? '/api/companies/' ~ object.getCompanies().first().getId() ~ '/users/' ~ object.getId() : '')",
    exclusion: new Hateoas\Exclusion(groups: ['company:read', 'user:read']),
)]
#[Hateoas\Relation(
    name: 'company_detail',
    href: "expr(object.getCompanies() && object.getCompanies().count() > 0 ? '/api/companies/' ~ object.getCompanies().first().getId() : '')",
    exclusion: new Hateoas\Exclusion(groups: ['company:read']),
)]
#[Hateoas\Relation(
    name: 'company_users',
    href: "expr(object.getCompanies().first() ? '/api/companies/' ~ object.getCompanies().first().getId() ~ '/users' : '')",
    exclusion: new Hateoas\Exclusion(groups: ['company:list']),
)]
#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Users
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez renseigner un prénom.')]
    #[Assert\Length(max: 255, maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['user:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le nom de famille ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['user:read'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'L\'email "{{ value }}" n\'est pas valide.')]
    #[Assert\Length(max: 255, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['user:read'])]
    private ?string $email = null;

    /**
     * @var Collection<int, Company>
     */
    #[ORM\ManyToMany(targetEntity: Company::class, inversedBy: 'users')]
    #[Groups(['user:write', 'user:read_no_companies'])]
    private Collection $companies;

    public function __construct()
    {
        $this->companies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName ? trim($lastName) : null;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email ? trim($email) : null;

        return $this;
    }

    /**
     * @return Collection<int, Company>
     */
    public function getCompanies(): Collection
    {
        return $this->companies;
    }

    public function addCompany(Company $company): self
    {
        if (!$this->companies->contains($company)) {
            $this->companies->add($company);
        }

        return $this;
    }

    public function removeCompany(Company $company): self
    {
        $this->companies->removeElement($company);

        return $this;
    }
}
