/**
 * SearchPanel — the site search UI used inside the NavBar. Renders an animated
 * slide-down bar with a debounced live-results dropdown (queries /api/search),
 * keyboard navigation, and Enter-to-full-results-page. The panel is always
 * mounted and animates between open/closed so the open is smooth (no pop-in).
 */
import { useState, useEffect, useRef, useCallback } from 'react'
import { router } from '@inertiajs/react'
import axios from 'axios'
import { faSpinner } from '@fortawesome/free-solid-svg-icons'
import Icon from './Icon'
import CoverImage from '@/Components/Common/CoverImage'

interface SearchResult {
    type: string
    title: string
    slug: string
    url: string
    description?: string
    thumbnail?: string
}

interface Props {
    open: boolean
    onClose: () => void
    transparent?: boolean
}

const DEBOUNCE_MS = 250
const MIN_CHARS = 2

const SearchPanel = ({ open, onClose, transparent }: Props) => {
    const [value, setValue] = useState('')
    const [results, setResults] = useState<SearchResult[]>([])
    const [loading, setLoading] = useState(false)
    const [highlighted, setHighlighted] = useState(-1)
    const inputRef = useRef<HTMLInputElement>(null)

    // Focus the input when the panel opens; clear everything when it closes.
    useEffect(() => {
        if (open) {
            inputRef.current?.focus()
        } else {
            setValue('')
            setResults([])
            setHighlighted(-1)
            setLoading(false)
        }
    }, [open])

    // Debounced live search. Cancels the in-flight timer AND request on every
    // keystroke so results can't arrive out of order (a later query always wins).
    useEffect(() => {
        const query = value.trim()
        if (query.length < MIN_CHARS) {
            setResults([])
            setLoading(false)
            return
        }
        setLoading(true)
        const controller = new AbortController()
        const handle = setTimeout(() => {
            axios
                .get('/api/search', { params: { q: query }, signal: controller.signal })
                .then(({ data }) => {
                    setResults(data.results ?? [])
                    setHighlighted(-1)
                })
                .catch(error => {
                    if (!axios.isCancel(error)) setResults([])
                })
                .finally(() => {
                    // Don't clear the spinner if this request was superseded —
                    // the newer request has already set loading=true.
                    if (!controller.signal.aborted) setLoading(false)
                })
        }, DEBOUNCE_MS)
        return () => {
            clearTimeout(handle)
            controller.abort()
        }
    }, [value])

    /** Navigate to the full /search results page for the current query. */
    const goToFullResults = useCallback(() => {
        const query = value.trim()
        if (!query) return
        router.get('/search', { q: query })
        onClose()
    }, [value, onClose])

    /** Navigate straight to a chosen result. */
    const selectResult = useCallback((result: SearchResult) => {
        router.visit(result.url)
        onClose()
    }, [onClose])

    const handleKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault()
            setHighlighted(prev => Math.min(prev + 1, results.length - 1))
        } else if (event.key === 'ArrowUp') {
            event.preventDefault()
            setHighlighted(prev => Math.max(prev - 1, -1))
        } else if (event.key === 'Enter') {
            event.preventDefault()
            if (highlighted >= 0 && results[highlighted]) {
                selectResult(results[highlighted])
            } else {
                goToFullResults()
            }
        } else if (event.key === 'Escape') {
            onClose()
        }
    }

    const showDropdown = open && value.trim().length >= MIN_CHARS

    return (
        <div
            className={[
                'overflow-hidden transition-all duration-300 ease-out',
                open ? 'max-h-[75vh] opacity-100' : 'max-h-0 opacity-0',
                transparent ? 'bg-primary/95 backdrop-blur-sm' : 'bg-primary',
            ].join(' ')}
        >
            <div className="container mx-auto py-3">
                <label htmlFor="site-search" className="sr-only">Search</label>
                <input
                    ref={inputRef}
                    id="site-search"
                    name="q"
                    type="text"
                    value={value}
                    onChange={event => setValue(event.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Search destinations, articles..."
                    autoComplete="off"
                    className="w-full px-4 py-2 rounded-md text-sm text-gray-900 placeholder:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-lighter shadow-sm"
                />

                {showDropdown && (
                    <div className="mt-2 rounded-md bg-white shadow-lg max-h-[60vh] overflow-y-auto">
                        {loading && (
                            <div className="flex items-center gap-x-2 px-4 py-3 text-sm text-gray-500">
                                <Icon icon={faSpinner} size="size-4" customClasses="animate-spin" />
                                Searching…
                            </div>
                        )}

                        {!loading && results.length === 0 && (
                            <p className="px-4 py-3 text-sm text-gray-500">No results for "{value.trim()}"</p>
                        )}

                        {!loading && results.map((result, index) => (
                            <button
                                key={`${result.type}-${result.slug}`}
                                type="button"
                                onClick={() => selectResult(result)}
                                onMouseEnter={() => setHighlighted(index)}
                                className={[
                                    'w-full flex items-center gap-x-3 px-4 py-2 text-left transition-colors',
                                    highlighted === index ? 'bg-primary-lightest' : 'hover:bg-gray-50',
                                ].join(' ')}
                            >
                                {result.thumbnail
                                    ? <CoverImage image={result.thumbnail} alt="" className="w-10 h-10 rounded flex-shrink-0" />
                                    : <span className="w-10 h-10 rounded bg-gray-100 flex-shrink-0" />}
                                <span className="min-w-0">
                                    <span className={`block text-[0.65rem] font-semibold uppercase tracking-wide ${result.type === 'spot_guide' ? 'text-primary' : 'text-orange'}`}>
                                        {result.type === 'spot_guide' ? 'Destination' : 'Blog'}
                                    </span>
                                    <span className="block text-sm font-medium text-secondary truncate">{result.title}</span>
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    )
}

export default SearchPanel
