import { useEffect, useState } from 'react';

/** The prototype's page proportion, used until the real image tells us its own. */
export const DEFAULT_PAGE_RATIO = 3213 / 6260;

/**
 * Width/height of the image at `url`, so the stage takes the artwork's real proportion and never crops it.
 * Falls back to the default page ratio while loading or when the image cannot be read.
 */
export function useImageRatio(url: string | null | undefined, fallback = DEFAULT_PAGE_RATIO): number {
    const [ratio, setRatio] = useState(fallback);
    useEffect(() => {
        if (!url) return;
        let alive = true;
        const im = new Image();
        im.onload = () => {
            if (alive && im.naturalWidth > 0 && im.naturalHeight > 0) setRatio(im.naturalWidth / im.naturalHeight);
        };
        im.src = url;
        return () => {
            alive = false;
        };
    }, [url]);
    return ratio;
}
