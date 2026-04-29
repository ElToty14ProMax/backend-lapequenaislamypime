<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Discount;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCurrencies();
        $this->seedUsers();
        $categories = $this->seedCategories();
        $products = $this->seedProducts($categories);
        $this->seedDiscounts($categories, $products);
    }

    private function seedCurrencies(): void
    {
        Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'Dolar estadounidense',
            'symbol' => '$',
            'decimals' => 2,
            'is_active' => true,
        ]);

        Currency::updateOrCreate(['code' => 'CUP'], [
            'name' => 'Peso cubano',
            'symbol' => 'CUP',
            'decimals' => 2,
            'is_active' => true,
        ]);

        ExchangeRate::updateOrCreate([
            'base_currency' => 'USD',
            'quote_currency' => 'CUP',
            'valid_to' => null,
        ], [
            'rate' => 120.00000000,
            'source' => 'manual_seed',
            'valid_from' => now(),
        ]);
    }

    private function seedUsers(): void
    {
        $this->seedAdminFromEnvironment();
        $customer = $this->seedDemoCustomerFromEnvironment();

        if (! $customer) {
            return;
        }

        Address::updateOrCreate([
            'user_id' => $customer->id,
            'label' => 'Casa',
        ], [
            'recipient_name' => 'Cliente Demo',
            'phone' => '+5355551234',
            'country' => 'Cuba',
            'province' => 'La Habana',
            'municipality' => 'Centro Habana',
            'street' => 'Calle 23 #123',
            'between_streets' => 'L y M',
            'reference' => 'Puerta azul, segundo piso.',
            'postal_code' => '10400',
            'latitude' => 23.1136000,
            'longitude' => -82.3666000,
            'is_default' => true,
        ]);
    }

    private function seedAdminFromEnvironment(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            return;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => env('ADMIN_NAME', 'Admin'),
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function seedDemoCustomerFromEnvironment(): ?User
    {
        if (! filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $email = env('DEMO_CUSTOMER_EMAIL');
        $password = env('DEMO_CUSTOMER_PASSWORD');

        if (! $email || ! $password) {
            return null;
        }

        return User::updateOrCreate(['email' => $email], [
            'name' => env('DEMO_CUSTOMER_NAME', 'Cliente Demo'),
            'phone' => env('DEMO_CUSTOMER_PHONE'),
            'password' => Hash::make($password),
            'role' => User::ROLE_CUSTOMER,
            'active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $definitions = [
            'alimentos' => ['Alimentos', null, 'Comida, bebidas y productos de consumo diario.', 10],
            'despensa' => ['Despensa', 'alimentos', 'Productos basicos para cocinar y abastecer el hogar.', 10],
            'carnes-y-proteinas' => ['Carnes y proteinas', 'alimentos', 'Carnes, embutidos, huevos y proteinas para comidas fuertes.', 20],
            'lacteos-y-frios' => ['Lacteos y frios', 'alimentos', 'Quesos, leche, yogures y productos refrigerados.', 30],
            'bebidas' => ['Bebidas', 'alimentos', 'Refrescos, jugos, maltas, aguas y bebidas para compartir.', 40],
            'dulces-y-snacks' => ['Dulces y snacks', 'alimentos', 'Galletas, caramelos, chocolates y meriendas.', 50],
            'aseo-personal' => ['Aseo personal', null, 'Cuidado diario, higiene y perfumeria basica.', 20],
            'limpieza-del-hogar' => ['Limpieza del hogar', null, 'Detergentes, desinfectantes y utiles de limpieza.', 30],
            'combos' => ['Combos', null, 'Selecciones listas para compras rapidas.', 40],
        ];

        $categories = [];

        foreach ($definitions as $slug => [$name, $parentSlug, $description, $sortOrder]) {
            $categories[$slug] = Category::updateOrCreate(['slug' => $slug], [
                'parent_id' => $parentSlug ? $categories[$parentSlug]->id : null,
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
        }

        return $categories;
    }

    /**
     * @param array<string, Category> $categories
     * @return array<string, Product>
     */
    private function seedProducts(array $categories): array
    {
        $products = [
            ['DEMO-ARROZ-1KG', 'despensa', 'Arroz 1kg', 'Producto basico para comidas diarias.', 'La Isla', 'kg', 120, 14400, 50, 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-FRIJOLES-NEGROS-1KG', 'despensa', 'Frijoles negros 1kg', 'Granos seleccionados para potajes y guarniciones.', 'La Isla', 'kg', 280, 33600, 40, 'https://images.unsplash.com/photo-1604176354204-9268737828e4?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-ACEITE-1L', 'despensa', 'Aceite vegetal 1L', 'Aceite vegetal para cocinar, freir y preparar alimentos.', 'Cocina Plus', 'botella', 360, 43200, 30, 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-AZUCAR-1KG', 'despensa', 'Azucar blanca 1kg', 'Azucar refinada para bebidas, postres y cocina.', 'Dulce Hogar', 'kg', 140, 16800, 60, 'https://images.unsplash.com/photo-1615485290382-441e4d049cb5?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-PASTA-500G', 'despensa', 'Pasta corta 500g', 'Pasta seca ideal para comidas rapidas.', 'Mesa Lista', 'paquete', 180, 21600, 45, 'https://images.unsplash.com/photo-1551462147-37885acc36f1?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-CAFE-250G', 'despensa', 'Cafe molido 250g', 'Cafe molido de aroma intenso para colar.', 'Serrano', 'paquete', 420, 50400, 35, 'https://images.unsplash.com/photo-1447933601403-0c6688de566e?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-POLLO-2KG', 'carnes-y-proteinas', 'Pollo troceado 2kg', 'Bolsa de pollo troceado congelado.', 'Fresco Market', 'bolsa', 980, 117600, 20, 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-HUEVOS-30', 'carnes-y-proteinas', 'Huevos 30 unidades', 'Carton de huevos frescos para el hogar.', 'Granja Real', 'carton', 650, 78000, 25, 'https://images.unsplash.com/photo-1587486913049-53fc88980cfc?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-SALCHICHAS-500G', 'carnes-y-proteinas', 'Salchichas 500g', 'Salchichas refrigeradas para desayunos y meriendas.', 'Fresco Market', 'paquete', 390, 46800, 18, 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-LECHE-1L', 'lacteos-y-frios', 'Leche UHT 1L', 'Leche larga vida para toda la familia.', 'Vaquita', 'caja', 240, 28800, 36, 'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-QUESO-500G', 'lacteos-y-frios', 'Queso gouda 500g', 'Queso semiduro para sandwiches y cocina.', 'Vaquita', 'pieza', 720, 86400, 16, 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-YOGUR-1L', 'lacteos-y-frios', 'Yogur natural 1L', 'Yogur natural refrigerado.', 'Vaquita', 'botella', 310, 37200, 22, 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-REFRESCO-COLA-1.5L', 'bebidas', 'Refresco cola 1.5L', 'Refresco sabor cola para compartir.', 'Tropical', 'botella', 260, 31200, 48, 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-MALTA-6U', 'bebidas', 'Malta pack 6 unidades', 'Pack de maltas individuales.', 'Tropical', 'pack', 540, 64800, 20, 'https://images.unsplash.com/photo-1608270586620-248524c67de9?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-AGUA-5L', 'bebidas', 'Agua mineral 5L', 'Agua mineral en formato familiar.', 'Aqua Clara', 'botellon', 220, 26400, 32, 'https://images.unsplash.com/photo-1564419320461-6870880221ad?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-GALLETAS-CHOCOLATE', 'dulces-y-snacks', 'Galletas chocolate 200g', 'Galletas dulces con sabor a chocolate.', 'Merienda', 'paquete', 190, 22800, 55, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-CHOCOLATE-BARRA', 'dulces-y-snacks', 'Chocolate en barra 100g', 'Chocolate dulce para merienda o regalo.', 'Merienda', 'barra', 210, 25200, 44, 'https://images.unsplash.com/photo-1606312619070-d48b4c652a52?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-JABON-3U', 'aseo-personal', 'Jabon de bano pack 3', 'Pack de jabones para higiene diaria.', 'Hogar Limpio', 'pack', 330, 39600, 28, 'https://images.unsplash.com/photo-1607006483224-4ff2f5d10f70?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-CHAMPU-400ML', 'aseo-personal', 'Champu 400ml', 'Champu familiar para uso diario.', 'Brisa', 'botella', 460, 55200, 24, 'https://images.unsplash.com/photo-1526947425960-945c6e72858f?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-DETERGENTE-1KG', 'limpieza-del-hogar', 'Detergente en polvo 1kg', 'Detergente para lavado de ropa.', 'Hogar Limpio', 'paquete', 390, 46800, 30, 'https://images.unsplash.com/photo-1585421514284-efb74c2b69ba?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-DESINFECTANTE-1L', 'limpieza-del-hogar', 'Desinfectante 1L', 'Desinfectante multiuso para superficies.', 'Hogar Limpio', 'botella', 290, 34800, 34, 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-COMBO-DESPENSA', 'combos', 'Combo despensa familiar', 'Arroz, frijoles, aceite, pasta, azucar y cafe para reponer la despensa.', 'La Pequena Isla', 'combo', 1390, 166800, 12, 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=80'],
            ['DEMO-COMBO-DESAYUNO', 'combos', 'Combo desayuno', 'Cafe, leche, galletas, huevos y yogur para varios dias.', 'La Pequena Isla', 'combo', 1190, 142800, 10, 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=900&q=80'],
        ];

        $seeded = [];

        foreach ($products as [$sku, $categorySlug, $name, $description, $brand, $unit, $usdCents, $cupCents, $stock, $imageUrl]) {
            $product = Product::updateOrCreate(['sku' => $sku], [
                'category_id' => $categories[$categorySlug]->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
                'brand' => $brand,
                'unit' => $unit,
                'status' => 'active',
                'track_inventory' => true,
                'stock' => $stock,
                'low_stock_threshold' => 5,
                'price_usd_cents' => $usdCents,
                'price_cup_cents' => $cupCents,
                'metadata' => [
                    'demo' => true,
                    'featured' => in_array($sku, ['DEMO-COMBO-DESPENSA', 'DEMO-POLLO-2KG', 'DEMO-CAFE-250G'], true),
                ],
            ]);

            $product->images()->updateOrCreate(['url' => $imageUrl], [
                'alt' => $name,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            $seeded[$sku] = $product;
        }

        return $seeded;
    }

    /**
     * @param array<string, Category> $categories
     * @param array<string, Product> $products
     */
    private function seedDiscounts(array $categories, array $products): void
    {
        Discount::updateOrCreate(['code' => 'BIENVENIDA10'], [
            'category_id' => null,
            'product_id' => null,
            'name' => 'Bienvenida 10%',
            'type' => 'percent',
            'value' => 10,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(3),
            'usage_limit' => 500,
            'is_active' => true,
        ]);

        Discount::updateOrCreate(['code' => 'DESPENSA5'], [
            'category_id' => $categories['despensa']->id,
            'product_id' => null,
            'name' => 'Descuento despensa',
            'type' => 'percent',
            'value' => 5,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'usage_limit' => null,
            'is_active' => true,
        ]);

        Discount::updateOrCreate(['code' => 'COMBO2'], [
            'category_id' => null,
            'product_id' => $products['DEMO-COMBO-DESPENSA']->id,
            'name' => 'Ahorro combo familiar',
            'type' => 'fixed',
            'value' => 2,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'usage_limit' => 100,
            'is_active' => true,
        ]);
    }
}
