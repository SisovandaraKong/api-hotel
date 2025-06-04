<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    public function uploadImage(Request $request)
    {
        $path = $request->file('image')->storePublicly('public/images');
        return response()->json([
            'path'=> "https://romsaydev.s3.us-east-1.amazonaws.com/$path",     
            'msg' => 'Image uploaded successfully',
        ]);
    }  
    
    // get all images
    public function getAllImages()
    {
        $files = Storage::files('public/images');
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
