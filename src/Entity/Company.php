<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[Hateoas\Relation(
    name: 'company_detail',
    href: "expr('/api/companies/' ~ object.getId())",
    exclusion: new Hateoas\Exclusion(groups: ['company:list'])
)]
#[Hateoas\Relation(
    name: 'users',
    href: "expr('/api/companies/' ~ object.getId() ~ '/users')",
    exclusion: new Hateoas\Exclusion(groups: ['company:read', 'company:list'])
)]
#[Hateoas\Relation(
    name: 'company_delete',
    href: "expr('/api/companies/' ~ object.getId())",
    exclusion: new Hateoas\Exclusion(
        groups: ['company:list', 'company:read'],
        excludeIf: "expr(not is_granted('ROLE_ADMIN'))"),
)]
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'This email is already used by another company')]
#[ORM\HasLifecycleCallbacks]
class Company extends AbstractAccount
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez entrer le nom de la société')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom de la société doit comporter au moins {{ limit }} caractères',
        maxMessage: 'Le nom de la société doit comporter au maximum {{ limit }} caractères'
    )]
    #[Groups(['user:read', 'company:read', 'company:list'])]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Veuillez entrer une URL valide')]
    #[Groups(['company:read', 'company:list'])]
    private ?string $webSite = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['company:read', 'company:list'])]
    protected ?string $phone = null;

    /**
     * @var Collection<int, Users>
     */
    #[ORM\ManyToMany(targetEntity: Users::class, mappedBy: 'companies')]
    private Collection $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new ArrayCollection();
    }

    public function getRoles(): array
    {
        return ['ROLE_COMPANY'];
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
}
