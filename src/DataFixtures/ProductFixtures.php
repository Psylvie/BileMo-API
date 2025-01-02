<?php

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $imageDirectory = 'C:/xampp/htdocs/xampp/BileMo-API/public/images/products';
        $imageFiles = array_diff(scandir($imageDirectory), ['..', '.']);

        for ($i = 0; $i < 50; ++$i) {
            $product = new Product();
            $product->setName($faker->word)
            ->setDescription($faker->sentence)
            ->setModel($faker->word)
            ->setBrand($faker->company)
            ->setReference($faker->uuid)
            ->setPrice($faker->randomFloat(2, 10, 100))
            ->setDimension($faker->word)
            ->setStock($faker->numberBetween(0, 100))
            ->setIsAvailable($faker->boolean);

            if (!empty($imageFiles)) {
                $randomImage = $faker->randomElement($imageFiles);
                $product->setImage('/images/products/'.$randomImage);
            }
            $product->initializeTimestampable();

            $manager->persist($product);
        }

        $manager->flush();
    }
}
