<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Arr;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Auth;

class UserPhoto extends Model
{
    /** プライマリキーの型 */
    protected $keyType = 'string';

    /** IDのみ代入されないようにする*/
    protected $guarded = ['id'];

    /** IDの桁数 */
    const ID_LENGTH = 12;

    /** JSONに含めるアクセサ */
    protected $appends = [
        'url'
    ];

    /** JSONに含める属性 */
    protected $visible = [
        'id', 'owner', 'url'
    ];

    protected $perPage = 20;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (!Arr::get($this->attributes, 'id')) {
            $this->setId();
        }
    }

    /**
     * ランダムなID値をid属性に代入する
     */
    private function setId()
    {
        $this->attributes['id'] = $this->getRandomId();
    }

    /**
     * ランダムなID値を生成する
     * @return string
     */
    private function getRandomId()
    {
        $characters = array_merge(
            range(0, 9),
            range('a', 'z'),
            range('A', 'Z'),
            ['-', '_']
        );

        $length = count($characters);

        $id = "user_";

        for ($i = 0; $i < self::ID_LENGTH; $i++) {
            $id .= $characters[random_int(0, $length - 1)];
        }

        return $id;
    }

    // ここから下はリレーション
    // withで呼び出す
    /**
     * リレーションシップ - usersテーブル
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo('App\User', 'user_id', 'id', 'users');
    }

    /**
     * アクセサ - url
     * @return string
     */
    public function getUrlAttribute()
    {
        return Storage::cloud()->url($this->attributes['filename']);
    }
}
