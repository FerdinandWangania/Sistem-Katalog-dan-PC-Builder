// Mock promotions — base shape matches GET /api/promotions
// (title, brands, discount_percentage, start_date, end_date).
// banner_image + click_target_* follow PRD §8.3 (backend P2) so the hero
// image path is testable before the backend ships those fields.

export const mockPromotions = [
  {
    _id: 'mock-promo-1',
    title: 'Super Resurgence — RTX 40 Super Series',
    brands: ['NVIDIA', 'ASUS'],
    discount_percentage: 15,
    start_date: '2026-01-01T00:00:00.000Z',
    end_date: '2026-12-31T23:59:59.000Z',
    banner_image: '',
    click_target_type: 'brand',
    click_target_value: 'NVIDIA',
  },
  {
    _id: 'mock-promo-2',
    title: 'Rakitan Gaming Hemat',
    brands: ['AMD', 'MSI'],
    discount_percentage: 10,
    start_date: '2026-01-01T00:00:00.000Z',
    end_date: '2026-12-31T23:59:59.000Z',
    banner_image: '',
    click_target_type: 'category',
    click_target_value: 'CPU',
  },
];
