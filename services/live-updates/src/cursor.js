/** Redis stream IDs have two independent, unbounded integer components. */
export function compareCursors(left, right) {
    const a = left.split('-').map(BigInt);
    const b = right.split('-').map(BigInt);
    return a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0;
}
