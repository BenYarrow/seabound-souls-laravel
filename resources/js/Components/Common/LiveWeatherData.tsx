import { useEffect, useState } from 'react'
import axios from 'axios'
import { faSun, faWind } from '@fortawesome/free-solid-svg-icons'
import Icon from '@/Components/Common/Icon'

interface Props {
    latitude: number
    longitude: number
}

interface WeatherData {
    temp: number
    windSpeed: number
    windGust: number | null
    description: string
}

const LiveWeatherData = ({ latitude, longitude }: Props) => {
    const [weather, setWeather] = useState<WeatherData | null>(null)
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        axios
            .post('/api/live-weather', { lat: latitude, lon: longitude })
            .then(({ data }) => {
                setWeather({
                    temp: Math.round(data.main.temp),
                    windSpeed: Math.round(data.wind.speed * 1.94384),
                    windGust: data.wind.gust != null ? Math.round(data.wind.gust * 1.94384) : null,
                    description: data.weather?.[0]?.description ?? '',
                })
            })
            .catch(() => {})
            .finally(() => setLoading(false))
    }, [latitude, longitude])

    if (!loading && !weather) return null

    /* ── Loading skeleton ── */
    if (loading) {
        return (
            <>
                {/* Desktop */}
                <div className="hidden lg:flex absolute bottom-8 left-8 bg-secondary/90 backdrop-blur-sm border border-white/10 rounded-xl p-5 flex-col gap-3 animate-pulse">
                    <div className="h-4 w-20 bg-white/15 rounded" />
                    <div className="h-4 w-24 bg-white/15 rounded" />
                </div>
                {/* Mobile */}
                <div className="lg:hidden w-full bg-secondary/90 backdrop-blur-sm border-t border-white/10 flex gap-6 px-5 py-3 animate-pulse">
                    <div className="h-4 w-16 bg-white/15 rounded" />
                    <div className="h-4 w-20 bg-white/15 rounded" />
                </div>
            </>
        )
    }

    if (!weather) return null

    const windLabel = weather.windGust != null
        ? `${weather.windSpeed} kts (gusts ${weather.windGust} kts)`
        : `${weather.windSpeed} kts`

    return (
        <>
            {/* ── Desktop: absolute bottom-left panel ── */}
            <div className="hidden lg:flex absolute bottom-8 left-8 bg-secondary/90 backdrop-blur-sm border border-white/10 rounded-xl p-5 flex-col gap-2.5">
                {weather.description && (
                    <p className="text-white/40 text-[10px] uppercase tracking-wider mb-0.5">
                        {weather.description}
                    </p>
                )}
                <div className="flex items-center gap-3 text-white">
                    <Icon icon={faSun} size="size-4" customClasses="text-white/60 shrink-0" />
                    <span className="text-sm tabular-nums">{weather.temp} °C</span>
                </div>
                <div className="flex items-center gap-3 text-white">
                    <Icon icon={faWind} size="size-4" customClasses="text-white/60 shrink-0" />
                    <span className="text-sm tabular-nums">{windLabel}</span>
                </div>
            </div>

            {/* ── Mobile: horizontal bar ── */}
            <div className="lg:hidden w-full bg-secondary/90 backdrop-blur-sm border-t border-white/10 flex items-center gap-6 px-5 py-3">
                {weather.description && (
                    <p className="text-white/40 text-[10px] uppercase tracking-wider mr-1 hidden sm:block">
                        {weather.description}
                    </p>
                )}
                <div className="flex items-center gap-2 text-white">
                    <Icon icon={faSun} size="size-3.5" customClasses="text-white/60 shrink-0" />
                    <span className="text-xs tabular-nums">{weather.temp} °C</span>
                </div>
                <div className="flex items-center gap-2 text-white">
                    <Icon icon={faWind} size="size-3.5" customClasses="text-white/60 shrink-0" />
                    <span className="text-xs tabular-nums">{windLabel}</span>
                </div>
            </div>
        </>
    )
}

export default LiveWeatherData
