<?php
/**
 * Created by PhpStorm.
 * User: kate
 * Date: 4/6/2019
 * Time: 10:08 AM
 */

namespace App\Model;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $fillable = ['id', 'tag_name','description'];
    protected $hidden = [
        'created_at', 'updated_at',
    ];
    public function user()
    {
        return $this->belongsToMany(User::class, 'user_categories');
    }
}