/** A tag on a similar discussion, as SimilarController sends it: only those the reader may see. */
export interface KindredTag {
  id: string;
  name: string;
  slug: string;
  color: string | null;
  icon: string | null;
  isChild: boolean;
}

/** One similar discussion, as `GET /api/kindred/{id}` sends it. */
export interface KindredItem {
  id: string;
  title: string;
  slug: string;
  replyCount: number;
  lastPostedAt: string | null;
  tags: KindredTag[];
}

export interface KindredResponse {
  data: KindredItem[];
}
