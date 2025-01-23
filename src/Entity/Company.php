<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
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
    #[Groups(['user:read', 'company:read', 'company:write'])]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Veuillez entrer une URL valide')]
    #[Groups(['company:read', 'company:write'])]
    private ?string $webSite = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['company:read', 'company:write'])]
    protected ?string $phone = null;

    /**
     * @var Collection<int, Users>
     */
    #[ORM\ManyToMany(targetEntity: Users::class, mappedBy: 'companies')]
    #[Groups(['company:read', 'company:write'])]
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
