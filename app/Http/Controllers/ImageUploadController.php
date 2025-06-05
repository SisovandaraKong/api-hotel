<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    // public function uploadImage(Request $request)
    // {
    //     $path = $request->file('image')->storePublicly('public/images');
    //     return response()->json([
    //         'path'=> "https://romsaydev.s3.us-east-1.amazonaws.com/$path",     
    //         'msg' => 'Image uploaded successfully',
    //     ]);
    // }  
    public function uploadImage(Request $request)
{
    $path = $request->file('image')->storePublicly('public/images', 's3');

    return response()->json([
        'success' => true,
        'message' => 'Image uploaded successfully.',
        'data' => [
            'url' => "https://romsaydev.s3.us-east-1.amazonaws.com/$path"
        ]
    ], 201); // 201 Created
}

// get all images
public function getAllImages()
{
    $files = Storage::files('images');
    $images = array_map(function ($file) {
            return "https://romsaydev.s3.us-east-1.amazonaws.com/$file";
        }, $files);

        return response()->json([
            'success' => true,
            'data' => $images,
            'message' => 'Images retrieved successfully',
        ], 200);
    }
}
