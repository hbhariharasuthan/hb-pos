/**
 * Extract scalar id from PaginatedDropdown value (object or scalar).
 * Use when form uses emit-full-item but API expects id.
 * @param {*} val - Value from v-model (object, number, string, null)
 * @returns {number|string|null}
 */
export function toId(val) {
    if (val == null || val === '') return null;
    return typeof val === 'object' ? val?.id ?? null : val;
}
