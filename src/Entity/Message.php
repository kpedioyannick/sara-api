<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = 'text'; // text, image, audio

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column]
    private ?bool $isRead = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $conversationId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $receiver = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Coach $coach = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $recipient = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    private ?Request $request = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getConversationId(): ?string
    {
        return $this->conversationId;
    }

    public function setConversationId(?string $conversationId): static
    {
        $this->conversationId = $conversationId;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getReceiver(): ?User
    {
        return $this->receiver;
    }

    public function setReceiver(?User $receiver): static
    {
        $this->receiver = $receiver;
        return $this;
    }

    public function getCoach(): ?Coach
    {
        return $this->coach;
    }

    public function setCoach(?Coach $coach): static
    {
        $this->coach = $coach;
        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function setRequest(?Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'content' => $this->getContent(),
            'type' => $this->getType(),
            'filePath' => $this->getFilePath(),
            'isRead' => $this->isRead(),
            'conversationId' => $this->getConversationId(),
            'sender' => $this->getSender()?->toArray(),
            'receiver' => $this->getReceiver()?->toArray(),
            'coach' => $this->getCoach()?->toArray(),
            'recipient' => $this->getRecipient()?->toArray(),
            'requestId' => $this->getRequest()?->getId(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->getId(),
            'content' => $this->getContent(),
            'type' => $this->getType(),
            'filePath' => $this->getFilePath(),
            'isRead' => $this->isRead(),
            'conversationId' => $this->getConversationId(),
            'sender' => $this->getSender()?->toPublicArray(),
            'receiver' => $this->getReceiver()?->toPublicArray(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    public static function create(array $data, User $sender, User $receiver): self
    {
        $message = new self();
        $message->setContent($data['content'] ?? null);
        $message->setType($data['type'] ?? 'text');
        $message->setFilePath($data['filePath'] ?? null);
        $message->setSender($sender);
        $message->setReceiver($receiver);
        $message->setIsRead($data['isRead'] ?? false);
        $message->setConversationId($data['conversationId'] ?? null);
        
        return $message;
    }

    public static function createForCoach(array $data, Coach $coach, User $recipient): self
    {
        $message = new self();
        $message->setContent($data['content']);
        $message->setSender($coach);
        $message->setReceiver($recipient);
        $message->setCoach($coach);
        $message->setRecipient($recipient);
        $message->setIsRead($data['isRead'] ?? false);
        $message->setConversationId($data['conversation_id'] ?? null);
        
        return $message;
    }

    public static function createForRequest(array $data, User $sender, User $receiver, ?Request $request = null): self
    {
        $message = new self();
        $message->setContent($data['content']);
        $message->setSender($sender);
        $message->setReceiver($receiver);
        $message->setIsRead($data['isRead'] ?? false);
        $message->setConversationId($data['conversation_id'] ?? null);
        
        if ($request) {
            $message->setRequest($request);
            // Si c'est un coach qui envoie, définir aussi coach et recipient
            if ($sender instanceof Coach) {
                $message->setCoach($sender);
                $message->setRecipient($receiver);
            }
        }
        
        return $message;
    }
}
