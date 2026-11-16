<?php

namespace App\Repository\Eloquent;

use App\Models\User;
use App\Repository\BaseRepository;
use App\Repository\Contracts\UserRepositoryContract;

/** @extends BaseRepository<User> */
final class UserRepository extends BaseRepository implements UserRepositoryContract
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }
}
