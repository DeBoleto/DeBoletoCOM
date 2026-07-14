export function useSearch() {
  async function search(query) {
    if (!query || query.trim().length < 2) return []
    const q = query.trim()

    try {
      const response = await fetch(`/api/search?q=${encodeURIComponent(q)}`)
      if (!response.ok) return []
      return await response.json()
    } catch {
      return []
    }
  }

  return { search }
}
