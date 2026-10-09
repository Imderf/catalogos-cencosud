import { type BoxDto } from '@/types';

export interface DiffResult {
    state: 'cambios' | 'iguales' | 'error' | 'sin-imagen';
    boxes: (BoxDto & { cells: number })[];
}

/**
 * Pixel difference over a coarse grid, grouping contiguous cells into regions.
 * Same algorithm as the prototype: 180×350 sample, 6px cells, threshold 14 over 0–255.
 */
export async function detectChanges(srcA: string | null, srcB: string | null): Promise<DiffResult> {
    if (!srcA || !srcB) return { state: 'sin-imagen', boxes: [] };
    const W = 180;
    const H = 350;
    const CELL = 6;
    const THRESHOLD = 14;
    const load = (src: string) =>
        new Promise<HTMLImageElement>((resolve, reject) => {
            const im = new Image();
            im.crossOrigin = 'anonymous';
            im.onload = () => resolve(im);
            im.onerror = reject;
            im.src = src;
        });
    try {
        const [a, b] = await Promise.all([load(srcA), load(srcB)]);
        const px = (im: HTMLImageElement) => {
            const cv = document.createElement('canvas');
            cv.width = W;
            cv.height = H;
            const cx = cv.getContext('2d')!;
            cx.drawImage(im, 0, 0, W, H);
            return cx.getImageData(0, 0, W, H).data;
        };
        const da = px(a);
        const db = px(b);
        const cols = Math.floor(W / CELL);
        const rows = Math.floor(H / CELL);
        const grid: boolean[] = new Array(cols * rows).fill(false);
        for (let r = 0; r < rows; r++)
            for (let c = 0; c < cols; c++) {
                let sum = 0;
                let n = 0;
                for (let y = r * CELL; y < (r + 1) * CELL; y++)
                    for (let x = c * CELL; x < (c + 1) * CELL; x++) {
                        const i = (y * W + x) * 4;
                        sum += Math.abs(da[i] - db[i]) + Math.abs(da[i + 1] - db[i + 1]) + Math.abs(da[i + 2] - db[i + 2]);
                        n += 3;
                    }
                grid[r * cols + c] = sum / n > THRESHOLD;
            }
        const boxes: DiffResult['boxes'] = [];
        const seen: boolean[] = new Array(cols * rows).fill(false);
        for (let r = 0; r < rows; r++)
            for (let c = 0; c < cols; c++) {
                const i = r * cols + c;
                if (!grid[i] || seen[i]) continue;
                let minR = r,
                    maxR = r,
                    minC = c,
                    maxC = c,
                    count = 0;
                const stack = [i];
                seen[i] = true;
                while (stack.length) {
                    const k = stack.pop()!;
                    const kr = Math.floor(k / cols);
                    const kc = k % cols;
                    count++;
                    minR = Math.min(minR, kr);
                    maxR = Math.max(maxR, kr);
                    minC = Math.min(minC, kc);
                    maxC = Math.max(maxC, kc);
                    for (const [dr, dc] of [
                        [-1, 0],
                        [1, 0],
                        [0, -1],
                        [0, 1],
                        [-1, -1],
                        [-1, 1],
                        [1, -1],
                        [1, 1],
                    ]) {
                        const nr = kr + dr;
                        const nc = kc + dc;
                        if (nr < 0 || nc < 0 || nr >= rows || nc >= cols) continue;
                        const nk = nr * cols + nc;
                        if (grid[nk] && !seen[nk]) {
                            seen[nk] = true;
                            stack.push(nk);
                        }
                    }
                }
                if (count < 3) continue;
                boxes.push({ x: minC / cols, y: minR / rows, w: (maxC - minC + 1) / cols, h: (maxR - minR + 1) / rows, cells: count });
            }
        boxes.sort((p, q) => q.cells - p.cells);
        return { state: boxes.length ? 'cambios' : 'iguales', boxes: boxes.slice(0, 12) };
    } catch {
        return { state: 'error', boxes: [] };
    }
}
