/**
 * ChandraKala Jewellers — TypeScript Type Definitions
 */

export type MetalType = 'gold' | 'silver' | 'silver_925';

export interface JewelleryItem {
  id: number;
  name: string;
  image: string;
  description: string;
  weight: number;
  metal_type: MetalType;
  manual_price: number;
  calculated_price?: number;
  is_favourite: boolean;
  is_sold_out: boolean;
  purity?: string;
  stone?: string;
  category?: string;
  sku?: string;
  dimensions?: string;
  tags?: string[];
  created_at?: string;
}

export interface MetalRates {
  gold_24k_10g: number;
  gold_22k_10g: number;
  gold_18k_10g: number;
  silver_1kg: number;
  silver_10g: number;
  updated_at: string;
}

export interface UploadResponse {
  success: boolean;
  message?: string;
  files?: Array<{
    filename: string;
    original_name: string;
    url?: string;
  }>;
  errors?: string[];
}
