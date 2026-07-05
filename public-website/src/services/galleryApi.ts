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

type PublicGalleryApiPayload = Partial<PublicGalleryResponse> & {
  data?: Partial<PublicGalleryResponse>;
};

export async function getPublicGallery(): Promise<PublicGalleryResponse> {
  const payload = await requestJson<PublicGalleryApiPayload>('/gallery/public-list.php');
  const data = payload.data ?? payload;

  return {
    folders: Array.isArray(data.folders) ? data.folders : [],
    images: Array.isArray(data.images) ? data.images : [],
  };
}

export async function getPublicGalleryImages(): Promise<PublicGalleryImage[]> {
  const gallery = await getPublicGallery();
  return gallery.images.filter((image) => typeof image.image_path === 'string' && image.image_path.trim() !== '');
}
