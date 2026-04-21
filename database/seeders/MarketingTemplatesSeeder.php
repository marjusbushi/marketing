<?php

namespace Database\Seeders;

use App\Models\Marketing\Template;
use Illuminate\Database\Seeder;

/**
 * Seed the Visual Studio template library with a curated starter set.
 *
 * Each row is marked is_system=true so users cannot delete it from the
 * admin UI. The `source` column holds a Polotno JSON document (for photo
 * kinds) or a Remotion composition reference + default props (for video
 * kinds). Real Polotno JSON and Remotion compositions will be filled in
 * by subsequent frontend tasks — here we bootstrap with minimal but
 * valid payloads so the editor can load the seed immediately.
 *
 * `metadata` feeds Claude in Faza 2 (AI Smart) to pick the best-fitting
 * template for a given product and post type.
 */
class MarketingTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $payload) {
            Template::query()->updateOrCreate(
                ['slug' => $payload['slug']],
                $payload,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'slug'           => 'reel-product-showcase',
                'name'           => 'Reel — Product Showcase',
                'kind'           => 'reel',
                'engine'         => 'remotion',
                'source'         => [
                    'composition' => 'ProductShowcase',
                    'duration_in_frames' => 450, // 15s @ 30fps
                    'fps'   => 30,
                    'width' => 1080,
                    'height' => 1920,
                    'default_props' => [
                        'product_image' => null,
                        'product_name'  => 'Product name',
                        'product_price' => '—',
                    ],
                ],
                'metadata'       => [
                    'use_case'      => 'Produkt i ri ose drop i ri — shfaq me dinamike',
                    'fits_products' => ['new-arrival', 'drop', 'limited'],
                    'aspect'        => '9:16',
                    'duration'      => 15,
                    'notes'         => 'Hero shot + name + price + CTA. I dedikuar per IG Reel / TikTok.',
                ],
                'thumbnail_path' => null,
                'is_system'      => true,
                'is_active'      => true,
            ],
            [
                'slug'           => 'reel-quote',
                'name'           => 'Reel — Quote',
                'kind'           => 'reel',
                'engine'         => 'remotion',
                'source'         => [
                    'composition' => 'Quote',
                    'duration_in_frames' => 300, // 10s
                    'fps'   => 30,
                    'width' => 1080,
                    'height' => 1920,
                    'default_props' => [
                        'quote_text' => 'Quote text',
                        'author'     => null,
                    ],
                ],
                'metadata'       => [
                    'use_case'      => 'Inspirim/motivim ose brand voice content',
                    'fits_products' => [],
                    'aspect'        => '9:16',
                    'duration'      => 10,
                    'notes'         => 'Pa produkt; tekst dominant me brand typography.',
                ],
                'thumbnail_path' => null,
                'is_system'      => true,
                'is_active'      => true,
            ],
            [
                'slug'           => 'reel-before-after',
                'name'           => 'Reel — Before / After',
                'kind'           => 'reel',
                'engine'         => 'remotion',
                'source'         => [
                    'composition' => 'BeforeAfter',
                    'duration_in_frames' => 450,
                    'fps'   => 30,
                    'width' => 1080,
                    'height' => 1920,
                    'default_props' => [
                        'before_image' => null,
                        'after_image'  => null,
                        'caption'      => 'Before / After',
                    ],
                ],
                'metadata'       => [
                    'use_case'      => 'Transformim, comparison, styling',
                    'fits_products' => ['apparel', 'accessory'],
                    'aspect'        => '9:16',
                    'duration'      => 15,
                    'notes'         => 'Split reveal animation; ideal per makeover / outfit.',
                ],
                'thumbnail_path' => null,
                'is_system'      => true,
                'is_active'      => true,
            ],
            [
                'slug'           => 'carousel-drop',
                'name'           => 'Carousel — Drop',
                'kind'           => 'carousel',
                'engine'         => 'polotno',
                'source'         => [
                    'polotno' => [
                        'width' => 1080,
                        'height' => 1350, // 4:5
                        'pages'  => array_fill(0, 5, ['background' => '#000', 'children' => []]),
                    ],
                ],
                'metadata'       => [
                    'use_case'      => 'Lancim drop i ri — 5 slides: teaser, produkte, detaj, CTA',
                    'fits_products' => ['drop', 'collection'],
                    'aspect'        => '4:5',
                    'slides'        => 5,
                    'notes'         => 'Hero first, products 2-4, CTA 5.',
                ],
                'thumbnail_path' => null,
                'is_system'      => true,
                'is_active'      => true,
            ],
            [
                'slug'           => 'carousel-how-to',
                'name'           => 'Carousel — How To',
                'kind'           => 'carousel',
                'engine'         => 'polotno',
                'source'         => [
                    'polotno' => [
                        'width' => 1080,
                        'height' => 1080, // 1:1
                        'pages'  => array_fill(0, 4, ['background' => '#fff', 'children' => []]),
                    ],
                ],
                'metadata'       => [
                    'use_case'      => 'Edukim/how-to — 4 hapa, text-first',
                    'fits_products' => ['apparel', 'accessory', 'generic'],
                    'aspect'        => '1:1',
                    'slides'        => 4,
                    'notes'         => 'Stepx 1-4 me numerim ne header.',
                ],
                'thumbnail_path' => null,
                'is_system'      => true,
                'is_active'      => true,
            ],
            [
                'slug'           => 'quote-static',
                'name'           => 'Quote — Static',
                'kind'           => 'photo',
                'engine'         => 'polotno',
                'source'         => [
                    'polotno' => [
                        'width' => 1080,
                        'height' => 1080,
                        'pages' => [[
                            'background' => '#000',
                            'children' => [],
                        ]],
                    ],
                ],
                'metadata'       => [
                    'use_case'      => 'Quote post statik per brand voice',
                    'fits_products' => [],
                    'aspect'        => '1:1',
                    'duration'      => null,
                    'notes'         => 'Tekst dominant, pa foto produkti.',
                ],
                'thumbnail_path' => null,
                'is_system'      => true,
                'is_active'      => true,
            ],
            [
                'slug'           => 'story-sale',
                'name'           => 'Story — Sale',
                'kind'           => 'story',
                'engine'         => 'polotno',
                'source'         => [
                    'polotno' => [
                        'width' => 1080,
                        'height' => 1920, // 9:16
                        'pages' => [[
                            'background' => '#ef4444',
                            'children' => [],
                        ]],
                    ],
                ],
                'metadata'       => [
                    'use_case'      => 'Sale/promocion i shpejte — story 9:16',
                    'fits_products' => ['sale', 'discount'],
                    'aspect'        => '9:16',
                    'duration'      => null,
                    'notes'         => 'Swipe up / link CTA; dominant me ngjyren e brand-it.',
                ],
                'thumbnail_path' => null,
                'is_system'      => true,
                'is_active'      => true,
            ],
        ];
    }
}
