<?php

namespace App\Models;

use App\Enum\StatusVisibility;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\HasApiTokens;
use Mastodon;

/**
 * @property int     id
 * @property string  username
 * @property string  name
 * @property string  avatar
 * @property string  email
 * @property Carbon  email_verified_at
 * @property string  password
 * @property int     home_id
 * @property Carbon  privacy_ack_at
 * @property boolean always_dbl
 * @property integer default_status_visibility
 * @property boolean private_profile
 * @property boolean prevent_index
 * @property int     privacy_hide_days
 * @property string  language
 * @property Carbon  last_login
 */
class User extends Authenticatable implements MustVerifyEmail
{

    use Notifiable, HasApiTokens, HasFactory;

    protected $fillable = [
        'username', 'name', 'avatar', 'email', 'email_verified_at', 'password', 'home_id', 'privacy_ack_at',
        'always_dbl', 'default_status_visibility', 'private_profile', 'prevent_index', 'privacy_hide_days',
        'language', 'last_login',
    ];
    protected $hidden   = [
        'password', 'remember_token', 'email', 'email_verified_at', 'privacy_ack_at',
        'home_id', 'avatar', 'always_dbl', 'role', 'social_profile', 'created_at', 'updated_at', 'userInvisibleToMe'
    ];
    protected $appends  = [
        'averageSpeed', 'points', 'userInvisibleToMe', 'twitterUrl', 'mastodonUrl', 'train_distance', 'train_duration',
        'following', 'followPending', 'muted'
    ];
    protected $casts    = [
        'id'                        => 'integer',
        'email_verified_at'         => 'datetime',
        'privacy_ack_at'            => 'datetime',
        'always_dbl'                => 'boolean',
        'home_id'                   => 'integer',
        'private_profile'           => 'boolean',
        'default_status_visibility' => StatusVisibility::class,
        'prevent_index'             => 'boolean',
        'privacy_hide_days'         => 'integer',
        'role'                      => 'integer',
        'last_login'                => 'datetime',
    ];

    public function getTrainDistanceAttribute(): float {
        return TrainCheckin::whereIn('status_id', $this->statuses()->select('id'))
                           ->select('distance')
                           ->sum('distance');
    }

    public function statuses(): HasMany {
        return $this->hasMany(Status::class);
    }

    public function getTrainDurationAttribute(): float {
        return TrainCheckin::whereIn('status_id', $this->statuses()->select('id'))
                           ->select(['arrival', 'departure'])
                           ->get()
                           ->sum('duration');
    }

    /**
     * @return float
     * @deprecated Use speed variable at train_checkins instead
     */
    public function getAverageSpeedAttribute(): float {
        return $this->train_duration == 0 ? 0 : $this->train_distance / ($this->train_duration / 60);
    }

    public function socialProfile(): HasOne {
        if ($this->hasOne(SocialLoginProfile::class)->count() == 0) {
            SocialLoginProfile::create(['user_id' => $this->id]);
        }
        return $this->hasOne(SocialLoginProfile::class);
    }

    public function home(): HasOne {
        return $this->hasOne(TrainStation::class, 'id', 'home_id');
    }

    public function likes(): HasMany {
        return $this->hasMany(Like::class);
    }

    public function follows(): BelongsToMany {
        return $this->belongsToMany(__CLASS__, 'follows', 'user_id', 'follow_id');
    }

    public function mutedUsers(): BelongsToMany {
        return $this->belongsToMany(__CLASS__, 'user_mutes', 'user_id', 'muted_id');
    }

    public function followRequests(): HasMany {
        return $this->hasMany(FollowRequest::class, 'follow_id', 'id');
    }

    /**
     * @deprecated
     */
    public function followers(): HasMany {
        return $this->hasMany(Follow::class, 'follow_id', 'id');
    }

    /**
     * @deprecated
     */
    public function followings(): HasMany {
        return $this->hasMany(Follow::class, 'user_id', 'id');
    }

    public function sessions(): HasMany {
        return $this->hasMany(Session::class);
    }

    public function icsTokens(): HasMany {
        return $this->hasMany(IcsToken::class, 'user_id', 'id');
    }

    public function getPointsAttribute(): int {
        return TrainCheckin::whereIn('status_id', $this->statuses()->select('id'))
                           ->where('departure', '>=', Carbon::now()->subDays(7)->toIso8601String())
                           ->select('points')
                           ->sum('points');
    }

    /**
     * @untested
     * @todo test
     */
    public function userFollowings(): BelongsToMany {
        return $this->belongsToMany(__CLASS__, 'follows', 'user_id', 'follow_id');
    }

    /**
     * @untested
     * @todo test
     */
    public function userFollowers(): BelongsToMany {
        return $this->belongsToMany(__CLASS__, 'follows', 'follow_id', 'user_id');
    }

    /**
     * @untested
     * @todo test
     */
    public function userFollowRequests(): BelongsToMany {
        return $this->belongsToMany(__CLASS__, 'follow_requests', 'follow_id', 'user_id');
    }

    /**
     * @deprecated -> replaced by $user->can(...) / $user->cannot(...) / request()->user()->can(...) /
     *             request()->user()->cannot(...)
     */
    public function getUserInvisibleToMeAttribute(): bool {
        return !request()?->user()?->can('view', $this);
    }

    public function getFollowingAttribute(): bool {
        return (auth()->check() && $this->followers->contains('user_id', auth()->user()->id));
    }

    public function getFollowPendingAttribute(): bool {
        return (auth()->check() && $this->followRequests->contains('user_id', auth()->user()->id));
    }

    public function getMutedAttribute(): bool {
        return (auth()->check() && auth()->user()->mutedUsers->contains('id', $this->id));
    }

    /**
     * @return string|null
     * @deprecated
     */
    public function getTwitterUrlAttribute(): ?string {
        if ($this->socialProfile->twitter_id) {
            return "https://twitter.com/i/user/" . $this->socialProfile->twitter_id;
        }
        return null;
    }

    public function getMastodonUrlAttribute(): ?string {
        $mastodonUrl = null;
        if (!empty($this->socialProfile)
            && !empty($this->socialProfile->mastodon_token)
            && !empty($this->socialProfile->mastodon_id)) {
            try {
                $mastodonServer = MastodonServer::where('id', $this->socialProfile->mastodon_server)->first();
                if ($mastodonServer) {
                    $mastodonDomain      = $mastodonServer->domain;
                    $mastodonAccountInfo = Mastodon::domain($mastodonDomain)
                                                   ->token($this->socialProfile->mastodon_token)
                                                   ->get("/accounts/" . $this->socialProfile->mastodon_id);
                    $mastodonUrl         = $mastodonAccountInfo["url"];
                }
            } catch (Exception $exception) {
                // The connection might be broken, or the instance is down, or $user has removed the api rights
                // but has not told us yet.
                Log::warning($exception);
            }
        }
        return $mastodonUrl;
    }

    /**
     * Get the entity's notifications.
     *
     * @return MorphMany
     */
    public function notifications(): MorphMany {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->orderBy('created_at', 'desc');
    }
}
