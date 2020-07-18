<?php

namespace App\Http\Controllers\V4DB;

use App\Anime;
use App\Http\HttpHelper;
use App\Http\HttpResponse;
use App\Http\Resources\V4\AnimeCharactersResource;
use App\Http\Resources\V4\CommonResource;
use App\Http\Resources\V4\ProfileFriendsResource;
use App\Http\Resources\V4\ProfileHistoryResource;
use App\Http\Resources\V4\ResultsResource;
use App\Profile;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jikan\Request\Anime\AnimeCharactersAndStaffRequest;
use Jikan\Request\User\RecentlyOnlineUsersRequest;
use Jikan\Request\User\UserAnimeListRequest;
use Jikan\Request\User\UserClubsRequest;
use Jikan\Request\User\UserMangaListRequest;
use Jikan\Request\User\UserProfileRequest;
use Jikan\Request\User\UserFriendsRequest;
use Jikan\Request\User\UserHistoryRequest;
use Jikan\Request\User\UserRecommendationsRequest;
use Jikan\Request\User\UserReviewsRequest;
use MongoDB\BSON\UTCDateTime;

class UserController extends Controller
{

    /**
     *  @OA\Get(
     *     path="/users/{username}",
     *     operationId="getUserProfile",
     *     tags={"users"},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Returns user profile",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied.",
     *     ),
     * ),
     */
    public function profile(Request $request, string $username)
    {
        $results = Profile::query()
            ->where('request_hash', $this->fingerprint)
            ->get();

        if (
            $results->isEmpty()
            || $this->isExpired($request, $results)
        ) {
            $response = Profile::scrape($username);

            if (HttpHelper::hasError($response)) {
                return HttpResponse::notFound($request);
            }

            if ($results->isEmpty()) {
                $meta = [
                    'createdAt' => new UTCDateTime(),
                    'modifiedAt' => new UTCDateTime(),
                    'request_hash' => $this->fingerprint
                ];
            }
            $meta['modifiedAt'] = new UTCDateTime();

            $response = $meta + $response;

            if ($results->isEmpty()) {
                Profile::query()
                    ->insert($response);
            }

            if ($this->isExpired($request, $results)) {
                Profile::query()
                    ->where('request_hash', $this->fingerprint)
                    ->update($response);
            }

            $results = Profile::query()
                ->where('request_hash', $this->fingerprint)
                ->get();
        }


        if ($results->isEmpty()) {
            return HttpResponse::notFound($request);
        }

        $response = (new \App\Http\Resources\V4\ProfileResource(
            $results->first()
        ))->response();

        return $this->prepareResponse(
            $response,
            $results,
            $request
        );
    }

    /**
     *  @OA\Get(
     *     path="/users/{username}/history/{type}",
     *     operationId="getUserHistory",
     *     tags={"users"},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Returns user history (past 30 days)",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied.",
     *     ),
     * ),
     */
    public function history(Request $request, string $username, ?string $type = null)
    {
        $type = strtolower($type);
        if (!is_null($type) && !\in_array($type, ['anime', 'manga'])) {
            return HttpResponse::badRequest($request);
        }

        $results = DB::table($this->getRouteTable($request))
            ->where('request_hash', $this->fingerprint)
            ->get();

        if (
            $results->isEmpty()
            || $this->isExpired($request, $results)
        ) {
            $data = ['history'=>$this->jikan->getUserHistory(new UserHistoryRequest($username, $type))];
            $response = \json_decode($this->serializer->serialize($data, 'json'), true);

            if (HttpHelper::hasError($response)) {
                return HttpResponse::notFound($request);
            }

            if ($results->isEmpty()) {
                $meta = [
                    'createdAt' => new UTCDateTime(),
                    'modifiedAt' => new UTCDateTime(),
                    'request_hash' => $this->fingerprint
                ];
            }
            $meta['modifiedAt'] = new UTCDateTime();

            $response = $meta + $response;

            if ($results->isEmpty()) {
                DB::table($this->getRouteTable($request))
                    ->insert($response);
            }

            if ($this->isExpired($request, $results)) {
                DB::table($this->getRouteTable($request))
                    ->where('request_hash', $this->fingerprint)
                    ->update($response);
            }

            $results = DB::table($this->getRouteTable($request))
                ->where('request_hash', $this->fingerprint)
                ->get();
        }

        $response = (new ProfileHistoryResource(
            $results->first()
        ))->response();

        return $this->prepareResponse(
            $response,
            $results,
            $request
        );
    }

    /**
     *  @OA\Get(
     *     path="/users/{username}/friends",
     *     operationId="getUserFriends",
     *     tags={"users"},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Returns user friends",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied.",
     *     ),
     * ),
     *
     *  @OA\Schema(
     *      schema="user friends",
     *      description="User Friends",
     *
     *      allOf={
     *          @OA\Schema(ref="#/components/schemas/pagination"),
     *          @OA\Schema(
     *
     *
     *              @OA\Property(
     *                   property="data",
     *                   type="object",
     *
     *
     *                   @OA\Property(
     *                       property="url",
     *                       type="string",
     *                       description="MyAnimeList URL"
     *                   ),
     *                   @OA\Property(
     *                       property="username",
     *                       type="string",
     *                       description="MyAnimeList Username"
     *                   ),
     *                   @OA\Property(
     *                       property="image_url",
     *                       type="string",
     *                       description="Image URL"
     *                   ),
     *                   @OA\Property(
     *                       property="last_online",
     *                       type="string",
     *                       description="Last Online Date ISO8601"
     *                   ),
     *                   @OA\Property(
     *                       property="friends_since",
     *                       type="string",
     *                       description="Friends Since Date ISO8601"
     *                   ),
     *              ),
     *          ),
     *     }
     *  ),
     */
    public function friends(Request $request, string $username)
    {
        $results = DB::table($this->getRouteTable($request))
            ->where('request_hash', $this->fingerprint)
            ->get();

        if (
            $results->isEmpty()
            || $this->isExpired($request, $results)
        ) {
            $page = $request->get('page') ?? 1;
            $data = $this->jikan->getUserFriends(new UserFriendsRequest($username, $page));
            $response = \json_decode($this->serializer->serialize($data, 'json'), true);

            if (HttpHelper::hasError($response)) {
                return HttpResponse::notFound($request);
            }

            if ($results->isEmpty()) {
                $meta = [
                    'createdAt' => new UTCDateTime(),
                    'modifiedAt' => new UTCDateTime(),
                    'request_hash' => $this->fingerprint
                ];
            }
            $meta['modifiedAt'] = new UTCDateTime();

            $response = $meta + $response;

            if ($results->isEmpty()) {
                DB::table($this->getRouteTable($request))
                    ->insert($response);
            }

            if ($this->isExpired($request, $results)) {
                DB::table($this->getRouteTable($request))
                    ->where('request_hash', $this->fingerprint)
                    ->update($response);
            }

            $results = DB::table($this->getRouteTable($request))
                ->where('request_hash', $this->fingerprint)
                ->get();
        }

        $response = (new ResultsResource(
            $results->first()
        ))->response();

        return $this->prepareResponse(
            $response,
            $results,
            $request
        );
    }


    public function animelist(string $username, ?string $status = null, int $page = 1)
    {
        if (!is_null($status)) {
            $status = strtolower($status);

            if (!\in_array($status, ['all', 'watching', 'completed', 'onhold', 'dropped', 'plantowatch', 'ptw'])) {
                return response()->json([
                    'error' => 'Bad Request'
                ])->setStatusCode(400);
            }
        }
        $status = $this->listStatusToId($status);

        return response(
            $this->serializer->serialize(
                [
                    'anime' => $this->jikan->getUserAnimeList(
                        new UserAnimeListRequest($username, $page, $status)
                    )
                ],
                'json'
            )
        );
    }

    public function mangalist(string $username, ?string $status = null, int $page = 1)
    {
        if (!is_null($status)) {
            $status = strtolower($status);

            if (!\in_array($status, ['all', 'reading', 'completed', 'onhold', 'dropped', 'plantoread', 'ptr'])) {
                return response()->json([
                    'error' => 'Bad Request'
                ])->setStatusCode(400);
            }
        }
        $status = $this->listStatusToId($status);

        return response(
            $this->serializer->serialize(
                [
                    'manga' => $this->jikan->getUserMangaList(
                        new UserMangaListRequest($username, $page, $status)
                    )
                ],
                'json'
            )
        );
    }

    /**
     *  @OA\Get(
     *     path="/users/{username}/reviews",
     *     operationId="getUserReviews",
     *     tags={"reviews collection"},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Returns user reviews",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied.",
     *     ),
     * ),
     */
    public function reviews(Request $request, string $username)
    {
        $results = DB::table($this->getRouteTable($request))
            ->where('request_hash', $this->fingerprint)
            ->get();

        if (
            $results->isEmpty()
            || $this->isExpired($request, $results)
        ) {
            $page = $request->get('page') ?? 1;
            $data = $this->jikan->getUserReviews(new UserReviewsRequest($username, $page));
            $response = \json_decode($this->serializer->serialize($data, 'json'), true);

            if (HttpHelper::hasError($response)) {
                return HttpResponse::notFound($request);
            }

            if ($results->isEmpty()) {
                $meta = [
                    'createdAt' => new UTCDateTime(),
                    'modifiedAt' => new UTCDateTime(),
                    'request_hash' => $this->fingerprint
                ];
            }
            $meta['modifiedAt'] = new UTCDateTime();

            $response = $meta + $response;

            if ($results->isEmpty()) {
                DB::table($this->getRouteTable($request))
                    ->insert($response);
            }

            if ($this->isExpired($request, $results)) {
                DB::table($this->getRouteTable($request))
                    ->where('request_hash', $this->fingerprint)
                    ->update($response);
            }

            $results = DB::table($this->getRouteTable($request))
                ->where('request_hash', $this->fingerprint)
                ->get();
        }

        $response = (new ResultsResource(
            $results->first()
        ))->response();

        return $this->prepareResponse(
            $response,
            $results,
            $request
        );
    }

    /**
     *  @OA\Get(
     *     path="/users/{username}/recommendations",
     *     operationId="getUserRecommendations",
     *     tags={"users"},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Returns Recent Anime Recommendations",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied.",
     *     ),
     * ),
     *
     */
    public function recommendations(Request $request, string $username)
    {
        $results = DB::table($this->getRouteTable($request))
            ->where('request_hash', $this->fingerprint)
            ->get();

        if (
            $results->isEmpty()
            || $this->isExpired($request, $results)
        ) {
            $page = $request->get('page') ?? 1;
            $data = $this->jikan->getUserRecommendations(new UserRecommendationsRequest($username, $page));
            $response = \json_decode($this->serializer->serialize($data, 'json'), true);

            if (HttpHelper::hasError($response)) {
                return HttpResponse::notFound($request);
            }

            if ($results->isEmpty()) {
                $meta = [
                    'createdAt' => new UTCDateTime(),
                    'modifiedAt' => new UTCDateTime(),
                    'request_hash' => $this->fingerprint
                ];
            }
            $meta['modifiedAt'] = new UTCDateTime();

            $response = $meta + $response;

            if ($results->isEmpty()) {
                DB::table($this->getRouteTable($request))
                    ->insert($response);
            }

            if ($this->isExpired($request, $results)) {
                DB::table($this->getRouteTable($request))
                    ->where('request_hash', $this->fingerprint)
                    ->update($response);
            }

            $results = DB::table($this->getRouteTable($request))
                ->where('request_hash', $this->fingerprint)
                ->get();
        }

        $response = (new ResultsResource(
            $results->first()
        ))->response();

        return $this->prepareResponse(
            $response,
            $results,
            $request
        );
    }

    /**
     *  @OA\Get(
     *     path="/users/{username}/clubs",
     *     operationId="getUserClubs",
     *     tags={"users"},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Returns user clubs",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied.",
     *     ),
     * ),
     *
     *  @OA\Schema(
     *      schema="user clubs",
     *      description="User Clubs",
     *
     *      allOf={
     *          @OA\Schema(ref="#/components/schemas/pagination"),
     *          @OA\Schema(
     *
     *              @OA\Property(
     *                   property="data",
     *                   type="array",
     *                   @OA\Items(
     *                      type="object",
     *
     *                      @OA\Property(
     *                          property="mal_id",
     *                          type="integer",
     *                          description="MyAnimeList ID"
     *                      ),
     *                      @OA\Property(
     *                          property="name",
     *                          type="string",
     *                          description="Club Name"
     *                      ),
     *                  ),
     *              ),
     *          ),
     *     }
     *  ),
     */
    public function clubs(Request $request, string $username)
    {
        $results = DB::table($this->getRouteTable($request))
            ->where('request_hash', $this->fingerprint)
            ->get();

        if (
            $results->isEmpty()
            || $this->isExpired($request, $results)
        ) {
            $data = ['results' => $this->jikan->getUserClubs(new UserClubsRequest($username))];
            $response = \json_decode($this->serializer->serialize($data, 'json'), true);

            if (HttpHelper::hasError($response)) {
                return HttpResponse::notFound($request);
            }

            if ($results->isEmpty()) {
                $meta = [
                    'createdAt' => new UTCDateTime(),
                    'modifiedAt' => new UTCDateTime(),
                    'request_hash' => $this->fingerprint
                ];
            }
            $meta['modifiedAt'] = new UTCDateTime();

            $response = $meta + $response;

            if ($results->isEmpty()) {
                DB::table($this->getRouteTable($request))
                    ->insert($response);
            }

            if ($this->isExpired($request, $results)) {
                DB::table($this->getRouteTable($request))
                    ->where('request_hash', $this->fingerprint)
                    ->update($response);
            }

            $results = DB::table($this->getRouteTable($request))
                ->where('request_hash', $this->fingerprint)
                ->get();
        }

        $response = (new ResultsResource(
            $results->first()
        ))->response();

        return $this->prepareResponse(
            $response,
            $results,
            $request
        );
    }

    public function recentlyOnline(Request $request)
    {
        $results = DB::table($this->getRouteTable($request))
            ->where('request_hash', $this->fingerprint)
            ->get();

        if (
            $results->isEmpty()
            || $this->isExpired($request, $results)
        ) {
            $data = ['results'=>$this->jikan->getRecentOnlineUsers(new RecentlyOnlineUsersRequest())];
            $response = \json_decode($this->serializer->serialize($data, 'json'), true);

            if (HttpHelper::hasError($response)) {
                return HttpResponse::notFound($request);
            }

            if ($results->isEmpty()) {
                $meta = [
                    'createdAt' => new UTCDateTime(),
                    'modifiedAt' => new UTCDateTime(),
                    'request_hash' => $this->fingerprint
                ];
            }
            $meta['modifiedAt'] = new UTCDateTime();

            $response = $meta + $response;

            if ($results->isEmpty()) {
                DB::table($this->getRouteTable($request))
                    ->insert($response);
            }

            if ($this->isExpired($request, $results)) {
                DB::table($this->getRouteTable($request))
                    ->where('request_hash', $this->fingerprint)
                    ->update($response);
            }

            $results = DB::table($this->getRouteTable($request))
                ->where('request_hash', $this->fingerprint)
                ->get();
        }

        $response = (new ResultsResource(
            $results->first()
        ))->response();

        return $this->prepareResponse(
            $response,
            $results,
            $request
        );
    }

    private function listStatusToId(?string $status) : int
    {
        if (is_null($status)) {
            return 7;
        }

        switch ($status) {
            case 'all':
                return 7;
            case 'watching':
            case 'reading':
                return 1;
            case 'completed':
                return 2;
            case 'onhold':
                return 3;
            case 'dropped':
                return 4;
            case 'plantowatch':
            case 'ptw':
            case 'plantoread':
            case 'ptr':
                return 6;
            default:
                return 7;
        }
    }
}
