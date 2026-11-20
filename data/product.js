
const PRODUCTS = {
  // CCTV PRODUCTS
  1: {
    id: 1,
    title: "HIKVISION CCTV Camera 1080P Auto Tracking Night",
    price: 399,
    img: "cam.jpg",
    category: "CCTV",
    description: "Professional CCTV camera with auto-tracking and night vision capabilities",
    specs: "1080P, Auto Tracking, Night Vision, IP66 Rated",
    brand: "HIKVISION"
  },
  2: {
    id: 2,
    title: "CCTV Camera Pro 1080P Full HD",
    price: 599,
    img: "cam.jpg",
    category: "CCTV",
    description: "Full HD CCTV camera with advanced features",
    specs: "1080P, Full HD, Wide Angle, Motion Detection",
    brand: "CCTV Pro"
  },

  // SOLAR PRODUCTS
  3: {
    id: 3,
    title: "HIKVISION Solar Panel 200W Energy Storage",
    price: 3100,
    img: "solar.jpg",
    category: "Solar",
    description: "High-efficiency solar panel with integrated energy storage",
    specs: "200W, Energy Storage, Weatherproof, 25-Year Warranty",
    brand: "HIKVISION"
  },
  4: {
    id: 4,
    title: "Portable Solar Generator with Battery Backup",
    price: 5500,
    img: "solar2.jpg",
    category: "Solar",
    description: "Portable solar generator with built-in battery backup system",
    specs: "5000W, Battery Backup, Portable, Fast Charging",
    brand: "Solar Pro"
  }
};

/**
 * Get product by ID
 * @param {number} id - Product ID
 * @returns {Object} Product object or null if not found
 */
function getProductById(id) {
  return PRODUCTS[id] || null;
}

/**
 * Get all products by category
 * @param {string} category - 'CCTV' or 'Solar'
 * @returns {Array} Array of products in that category
 */
function getProductsByCategory(category) {
  return Object.values(PRODUCTS).filter(p => p.category === category);
}

/**
 * Get all products
 * @returns {Array} Array of all products
 */
function getAllProducts() {
  return Object.values(PRODUCTS);
}

/**
 * Get all categories
 * @returns {Array} Array of unique categories
 */
function getCategories() {
  return [...new Set(Object.values(PRODUCTS).map(p => p.category))];
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    PRODUCTS,
    getProductById,
    getProductsByCategory,
    getAllProducts,
    getCategories
  };
}