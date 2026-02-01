<?php
namespace Godyar\Models;

class Category extends BaseModel {
  public function all(): array {
    if (class_exists('\\Cache')) {
      return \Cache::remember('categories_all_v1', 3600, function () {
        return $this->db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
      });
    }
    return $this->db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
  }
}
