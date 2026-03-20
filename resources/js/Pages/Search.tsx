import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import { Link } from '@inertiajs/react'
import { router } from '@inertiajs/react'
import { useState, FormEvent } from 'react'

interface SearchResult {
    type: string
    title: string
    slug: string
    url: string
    description?: string
    thumbnail?: string
}

interface Props {
    query: string
    results: SearchResult[]
    meta: { title: string; description: string }
}

const Search = ({ query, results, meta }: Props) => {
    const [searchValue, setSearchValue] = useState(query)

    const handleSearch = (e: FormEvent) => {
        e.preventDefault()
        if (searchValue.trim()) {
            router.get('/search', { q: searchValue.trim() })
        }
    }

    return (
        <Layout title={meta.title} description={meta.description}>
            <div className="bg-primary py-16">
                <div className="container mx-auto">
                    <h1 className="text-white text-4xl md:text-5xl font-bold">Search</h1>
                </div>
            </div>

            <BlockWrapper>
                <form onSubmit={handleSearch} className="mb-8">
                    <div className="flex gap-x-3 max-w-xl">
                        <input
                            type="text"
                            value={searchValue}
                            onChange={e => setSearchValue(e.target.value)}
                            placeholder="Search destinations, articles..."
                            className="flex-1 px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                        <button type="submit" className="bg-primary text-white px-6 py-3 rounded-md font-semibold hover:bg-primary-darker transition-colors">
                            Search
                        </button>
                    </div>
                </form>

                {query && (
                    <p className="text-gray-600 mb-6 text-sm">
                        {results.length} result{results.length !== 1 ? 's' : ''} for "<strong>{query}</strong>"
                    </p>
                )}

                {results.length > 0 ? (
                    <div className="space-y-4">
                        {results.map((result, index) => (
                            <a key={index} href={result.url} className="flex gap-x-4 p-4 rounded-lg border border-gray-200 hover:border-primary transition-colors group">
                                {result.thumbnail && (
                                    <img src={result.thumbnail} alt={result.title} className="w-24 h-20 object-cover rounded-md flex-shrink-0" />
                                )}
                                <div>
                                    <span className={`text-xs font-semibold uppercase tracking-wide ${result.type === 'spot_guide' ? 'text-primary' : 'text-orange'}`}>
                                        {result.type === 'spot_guide' ? 'Destination' : 'Blog'}
                                    </span>
                                    <h3 className="text-lg font-bold text-secondary group-hover:text-primary transition-colors mt-1">{result.title}</h3>
                                    {result.description && <p className="text-gray-600 text-sm mt-1 line-clamp-2">{result.description}</p>}
                                </div>
                            </a>
                        ))}
                    </div>
                ) : query ? (
                    <div className="text-center py-16 text-gray-500">
                        <p className="text-xl">No results found for "{query}"</p>
                        <p className="text-sm mt-2">Try different keywords</p>
                    </div>
                ) : null}
            </BlockWrapper>
        </Layout>
    )
}

export default Search
