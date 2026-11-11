<?php

namespace App\Enums;

enum NotificationType: string
{
    case AccountFollower    = 'account_follower';

    case PostLike           = 'post_like';
}
