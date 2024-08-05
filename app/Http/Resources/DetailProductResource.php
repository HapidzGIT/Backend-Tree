<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->product ? $this->product->name : null,
            'description' => $this->description,
            'merchant' => $this->product && $this->product->merchant,
            'country_code' => $this->product->merchant->country_code,
            'merchant_name' => $this->product->merchant->merchant_name,
            'price' => $this->product ? $this->product->price : null,
            'status' => $this->product ? $this->product->status : null,
            'image_urls' => asset($this->image),

        ];
    }

    // public function with($request)
    // {
    //     return [
    //         'detail_Products' => $this->toArray($request)
    //     ];
    // }
}
