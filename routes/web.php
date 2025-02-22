<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Controllers\Frontend\AccountController;
use App\Http\Controllers\Frontend\EventController;
use App\Http\Controllers\Frontend\Export\ExportController;
use App\Http\Controllers\Frontend\IcsController;
use App\Http\Controllers\Frontend\LeaderboardController;
use App\Http\Controllers\Frontend\SettingsController;
use App\Http\Controllers\Frontend\Social\MastodonController;
use App\Http\Controllers\Frontend\Social\SocialController;
use App\Http\Controllers\Frontend\Social\TwitterController;
use App\Http\Controllers\Frontend\StatisticController;
use App\Http\Controllers\Frontend\Support\SupportController;
use App\Http\Controllers\Frontend\User\ProfilePictureController;
use App\Http\Controllers\FrontendStaticController;
use App\Http\Controllers\FrontendStatusController;
use App\Http\Controllers\FrontendTransportController;
use App\Http\Controllers\FrontendUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrivacyAgreementController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require_once realpath(__DIR__) . '/web/admin.php';

Route::get('/@{username}/picture', [ProfilePictureController::class, 'generateProfilePicture'])
     ->name('profile.picture');

Route::get('/', [FrontendStaticController::class, 'renderLandingPage'])
     ->name('static.welcome');

Route::view('/about', 'about')->name('static.about');

Route::permanentRedirect('/imprint', '/legal/');
Route::permanentRedirect('/privacy', '/legal/privacy-policy');
Route::prefix('legal')->group(function() {
    Route::view('/', 'legal.notice')
         ->name('legal.notice');
    Route::get('/privacy-policy', [PrivacyAgreementController::class, 'intercept'])
         ->name('legal.privacy');
});

Route::redirect('/profile/{username}', '/@{username}');
Route::get('/@{username}', [FrontendUserController::class, 'getProfilePage'])
     ->name('profile');

Route::get('/leaderboard', [LeaderboardController::class, 'renderLeaderboard'])
     ->name('leaderboard');

Route::get('/leaderboard/{date}', [LeaderboardController::class, 'renderMonthlyLeaderboard'])
     ->name('leaderboard.month');

Route::get('/statuses/active', [FrontendStatusController::class, 'getActiveStatuses'])
     ->name('statuses.active');

Route::get('/statuses/event/{eventSlug}', [FrontendStatusController::class, 'statusesByEvent'])
     ->name('statuses.byEvent');

Route::get('/events', [EventController::class, 'renderEventOverview'])
     ->name('events');

Auth::routes(['verify' => true]);

Route::get('/auth/redirect/twitter', [TwitterController::class, 'redirect']);
Route::get('/auth/redirect/mastodon', [MastodonController::class, 'redirect']);
Route::get('/callback/twitter', [TwitterController::class, 'callback']);
Route::get('/callback/mastodon', [MastodonController::class, 'callback']);

Route::get('/status/{id}', [FrontendStatusController::class, 'getStatus'])
     ->name('statuses.get');

Route::prefix('blog')->group(function() {
    Route::permanentRedirect('/', 'https://blog.traewelling.de')
         ->name('blog.all');

    Route::permanentRedirect('/{slug}', 'https://blog.traewelling.de/posts/{slug}')
         ->name('blog.show');

    Route::get('/cat/{category}', function($category) {
        return redirect('https://blog.traewelling.de/categories/' . strtolower($category), 301);
    })->name('blog.category');
});

/**
 * These routes can be used by logged in users although they have not signed the privacy policy yet.
 */
Route::middleware(['auth'])->group(function() {

    Route::get('/gdpr-intercept', [PrivacyAgreementController::class, 'intercept'])
         ->name('gdpr.intercept');

    Route::post('/gdpr-ack', [PrivacyAgreementController::class, 'ack'])
         ->name('gdpr.ack');

    Route::post('/settings/destroy', [AccountController::class, 'deleteUserAccount'])
         ->name('account.destroy');
});


Route::get('/ics', [IcsController::class, 'renderIcs'])
     ->name('ics');

/**
 * All of these routes can only be used by fully registered users.
 */
Route::middleware(['auth', 'privacy'])->group(function() {
    Route::post('/ics/createToken', [IcsController::class, 'createIcsToken'])
         ->name('ics.createToken');
    Route::post('/ics/revokeToken', [IcsController::class, 'revokeIcsToken'])
         ->name('ics.revokeToken');

    Route::post('/destroy/provider', [SocialController::class, 'destroyProvider'])
         ->name('provider.destroy');

    Route::prefix('stats')->group(static function() {
        Route::get('/', [StatisticController::class, 'renderMainStats'])
             ->name('stats');
        Route::get('/stations', [StatisticController::class, 'renderStations'])
             ->name('stats.stations');
    });

    Route::get('/support', [SupportController::class, 'renderSupportPage'])->name('support');
    Route::post('/support/submit', [SupportController::class, 'submit'])->name('support.submit');

    Route::post('/events/suggest', [EventController::class, 'suggestEvent'])
         ->name('events.suggest');

    Route::prefix('settings')->group(function() {
        Route::get('/', [SettingsController::class, 'renderSettings'])
             ->name('settings');
        Route::post('/', [SettingsController::class, 'updateMainSettings']);
        Route::post('/update/privacy', [SettingsController::class, 'updatePrivacySettings'])
             ->name('settings.privacy');

        Route::post('/password', [SettingsController::class, 'updatePassword'])
             ->name('password.change');

        Route::get('/follower', [\App\Http\Controllers\SettingsController::class, 'renderFollowerSettings'])
             ->name('settings.follower');
        Route::post('/follower/remove', [\App\Http\Controllers\SettingsController::class, 'removeFollower'])
             ->name('settings.follower.remove');
        Route::post('/follower/approve', [SettingsController::class, 'approveFollower'])
             ->name('settings.follower.approve');
        Route::post('/follower/reject', [SettingsController::class, 'rejectFollower'])
             ->name('settings.follower.reject');

        Route::post('/uploadProfileImage', [FrontendUserController::class, 'updateProfilePicture'])
             ->name('settings.upload-image');
        Route::get('/deleteProfilePicture', [UserController::class, 'deleteProfilePicture'])
             ->name('settings.delete-profile-picture');

        Route::post('/delsession', [UserController::class, 'deleteSession'])
             ->name('delsession');
        Route::post('/deltoken', [UserController::class, 'deleteToken'])
             ->name('deltoken');
    });

    Route::get('/dashboard', [FrontendStatusController::class, 'getDashboard'])
         ->name('dashboard');

    Route::get('/dashboard/global', [FrontendStatusController::class, 'getGlobalDashboard'])
         ->name('globaldashboard');

    Route::delete('/destroystatus', [FrontendStatusController::class, 'DeleteStatus'])
         ->name('status.delete');

    Route::post('/edit', [FrontendStatusController::class, 'EditStatus'])
         ->name('edit');

    Route::post('/createlike', [FrontendStatusController::class, 'createLike'])
         ->name('like.create');

    Route::post('/destroylike', [FrontendStatusController::class, 'DestroyLike'])
         ->name('like.destroy');

    Route::prefix('export')->group(function() {
        Route::get('/', [ExportController::class, 'renderForm'])->name('export.landing');
        Route::get('/generate', [ExportController::class, 'renderExport'])->name('export.generate');
    });

    Route::post('/createfollow', [FrontendUserController::class, 'CreateFollow'])
         ->name('follow.create');

    Route::post('/requestfollow', [FrontendUserController::class, 'requestFollow'])
         ->name('follow.request');

    Route::post('/destroyfollow', [FrontendUserController::class, 'destroyFollow'])
         ->name('follow.destroy');

    Route::get('/transport/train/autocomplete/{station}', [FrontendTransportController::class, 'TrainAutocomplete'])
         ->name('transport.train.autocomplete');

    Route::get('/trains/stationboard', [FrontendTransportController::class, 'TrainStationboard'])
         ->name('trains.stationboard');

    Route::get('/trains/nearby', [FrontendTransportController::class, 'StationByCoordinates'])
         ->name('trains.nearby');

    Route::get('/trains/trip', [FrontendTransportController::class, 'TrainTrip'])
         ->name('trains.trip');

    Route::post('/trains/checkin', [FrontendTransportController::class, 'TrainCheckin'])
         ->name('trains.checkin');

    Route::get('/trains/setHome/', [FrontendTransportController::class, 'setTrainHome'])
         ->name('user.setHome');

    Route::get('/notifications/latest', [NotificationController::class, 'renderLatest'])
         ->name('notifications.latest');

    Route::post('/notifications/toggleReadState/{id}', [NotificationController::class, 'toggleReadState'])
         ->name('notifications.toggleReadState');

    Route::post('/notifications/readAll', [NotificationController::class, 'readAll'])
         ->name('notifications.readAll');

    Route::get('/search/', [FrontendUserController::class, 'searchUser'])
         ->name('userSearch');

    Route::post('/user/mute', [\App\Http\Controllers\Frontend\UserController::class, 'muteUser'])
         ->name('user.mute');
    Route::post('/user/unmute', [\App\Http\Controllers\Frontend\UserController::class, 'unmuteUser'])
         ->name('user.unmute');
});

Route::get('/sitemap.xml', [SitemapController::class, 'renderSitemap']);
