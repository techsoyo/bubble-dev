<?php

declare(strict_types=1);

namespace Domain\ValueObjects;

class OAuthToken
{
  private string $accessToken;
  private ?string $refreshToken;
  private int $expiresIn;
  private string $tokenType;
  private ?int $createdAt;

  public function __construct(
    string $accessToken,
    ?string $refreshToken = null,
    int $expiresIn = 3600,
    string $tokenType = 'Bearer'
  ) {
    $this->accessToken = $accessToken;
    $this->refreshToken = $refreshToken;
    $this->expiresIn = $expiresIn;
    $this->tokenType = $tokenType;
    $this->createdAt = time();
  }

  public function getAccessToken(): string
  {
    return $this->accessToken;
  }

  public function getRefreshToken(): ?string
  {
    return $this->refreshToken;
  }

  public function getExpiresIn(): int
  {
    return $this->expiresIn;
  }

  public function getTokenType(): string
  {
    return $this->tokenType;
  }

  public function getCreatedAt(): ?int
  {
    return $this->createdAt;
  }

  public function isExpired(): bool
  {
    if (!$this->createdAt) {
      return false;
    }

    return (time() - $this->createdAt) >= $this->expiresIn;
  }

  public function getExpiresAt(): ?int
  {
    if (!$this->createdAt) {
      return null;
    }

    return $this->createdAt + $this->expiresIn;
  }

  public function toArray(): array
  {
    return [
      'access_token' => $this->accessToken,
      'refresh_token' => $this->refreshToken,
      'expires_in' => $this->expiresIn,
      'token_type' => $this->tokenType,
      'created_at' => $this->createdAt,
      'expires_at' => $this->getExpiresAt()
    ];
  }
}
