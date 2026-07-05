import { requestJson } from './apiClient';

export type PublicGalleryFolder = {
  id: number | string;
  name: string;
  slug: string;
};

export type PublicGalleryImage = {
  id: number | string;
  title?: string;
  image_path: string;
  folder_id?: number | string;
  folder_name?: string;
  folder_slug?: string;
};

export type PublicGalleryResponse = {
  folders: PublicGalleryFolder[];
  images: PublicGalleryImage[];
};

export async function getPublicGallery(): Promise<PublicGalleryResponse> {
  const payload = await requestJson<Partial<PublicGalleryResponse>>('/gallery/public-list.php');

  return {
    folders: Array.isArray(payload.folders) ? payload.folders : [],
    images: Array.isArray(payload.images) ? payload.images : [],
  };
}

export async function getPublicGalleryImages(): Promise<PublicGalleryImage[]> {
  const gallery = await getPublicGallery();
  return gallery.images.filter((image) => typeof image.image_path === 'string' && image.image_path.trim() !== '');
}
