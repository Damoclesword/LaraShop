<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'title', 'description', 'image', 'on_sale',
        'rating', 'sold_count', 'review_count', 'price'
    ];

    protected $casts = [
        'on_sale' => 'boolean'
    ];

    // One product has many skus
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }

    // One product has many skusAttributes
    public function skus_attributes()
    {
        return $this->hasMany(ProductSkuAttributes::class);
    }

    /**
     * This function turn ImageUrl into full links
     * When the blade template use '$product->image_url', this function will be called.
     * $this->image === $this->attributes['image']
     * @return mixed|string
     */
    public function getImageUrlAttribute()
    {
        if (Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }
        return Storage::disk('public')->url($this->image);
    }
}
