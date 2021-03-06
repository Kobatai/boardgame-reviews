<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\StoreShop;
use App\Shop;
use App\Photo;

use App\Http\Requests\StoreReview;
use App\Review;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ShopController extends Controller
{
    public function __construct()
    {
        // 認証に通す
        // authに通らなくても使えるもの
        $this->middleware('auth')->except(['index', 'show', 'create', 'search']);
    }

    /**
     * 店舗一覧取得
     */
    public function index()
    {
        $shops = Shop::with(['photos', 'likes'])->orderBy(Shop::CREATED_AT, 'desc')->paginate();

        return $shops;
    }

    /**
     * 店舗登録
     * @param StoreShop $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        // 写真の拡張子を取得
        $extension = $request->photo->extension();
        $photo = new Photo();
        // インスタンス生成時に割り振られたランダムなID値(prefixはshop)と本来の拡張子を組み合わせてファイル名とする
        $photo->filename = $photo->id . '.' . $extension;

        // S3にファイルを保存する publicで公開
        Storage::cloud()
            ->putFileAs('', $request->photo, $photo->filename, 'public');

        // データベースエラー時にファイル削除を行うため
        // トランザクションを利用する
        DB::beginTransaction();

        try {
            $shop = new Shop();
            $shop->fill($request->all())->save();
            // shopに登録後、紐づくphotoをsave
            $photo->shop_id = $shop->id;
            $shop->photos()->save($photo);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            // DBとの不整合を避けるためアップロードしたファイルを削除
            Storage::cloud()->delete($photo->filename);
            throw $exception;
        }

        // リソースの新規作成なので
        // レスポンスコードは201(CREATED)を返却する
        // vueでキャッチする
        return response($shop, 201);
    }

    /**
     * 店舗詳細
     * @param int $id
     * @return Shop
     */
    public function show(int $id)
    {
        $shop = Shop::where('id', $id)->with(['photos', 'reviews.author', 'likes'])->first();

        clock($shop);

        return $shop ?? abort(404);
    }

    /**
     * 店舗検索
     * @param string $keyword
     * @return Shop
     */
    public function search($keyword)
    {

        clock($keyword);

        $shops = Shop::where('shop_name', 'LIKE', '%' . $keyword . '%')->with(['photos', 'likes'])->get();

        clock($shops);

        return $shops;
    }

    /**
     * レビュー投稿
     * @param Shop $photo
     * @param StoreReview $request
     * @return \Illuminate\Http\Response
     */
    public function addReview(Shop $shop, StoreReview $request)
    {
        $review = new Review();
        $review->content = $request->get('content');
        $review->user_id = Auth::user()->id;
        $shop->reviews()->save($review);

        // authorリレーションをロードするためにコメントを取得しなおす
        $new_review = Review::where('id', $review->id)->with('author')->first();

        return response($new_review, 201);
    }

    /**
     * いいね
     * @param int $id
     * @return array
     */
    public function like(int $id)
    {
        $shop = Shop::where('id', $id)->with('likes')->first();

        if (!$shop) {
            abort(404);
        }

        // 一度detachしてからattachすることでユーザーにつき一個しかいいねが付かない
        $shop->likes()->detach(Auth::user()->id);
        $shop->likes()->attach(Auth::user()->id);

        return ["shop_id" => $id];
    }

    /**
     * いいね解除
     * @param int $id
     * @return array
     */
    public function unlike(int $id)
    {
        $shop = Shop::where('id', $id)->with('likes')->first();

        if (!$shop) {
            abort(404);
        }

        $shop->likes()->detach(Auth::user()->id);

        return ["shop_id" => $id];
    }
}
