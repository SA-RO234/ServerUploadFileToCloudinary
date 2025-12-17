<?php
namespace App\Config;
use Cloudinary\Cloudinary;

class CloudinaryConfig {
    private $cloudinary;
    
    public function __construct() {
        // Initialize Cloudinary with environment variables
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $_ENV['CLOUDINARY_NAME'] ?? '',
                'api_key'    => $_ENV['CLOUDINARY_KEY'] ?? '',
                'api_secret' => $_ENV['CLOUDINARY_SECRET'] ?? ''
            ],
        ]);
    }
    
 
    public function getCloudinary() {
        return $this->cloudinary;
    }
}