import { useMemo } from 'react'
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts'
import { prepareYearlyWindData, WeatherDataset } from '@/Helpers/weatherDataHelpers'
import type { SelectOption } from './FilterDataset'

interface Props {
    weatherData: WeatherDataset
    activeYear: number
    activeDestinations: SelectOption[]
    showAverageGustData: boolean
    activeWindUnit: string
    setActiveWindUnit: (unit: string) => void
    setShowAverageGustData: (state: boolean) => void
    colours: Record<string, string>
}

const unitOptions = ['kts', 'mph', 'kph']

const AXIS_TICK = { fill: 'rgba(0,0,0,0.6)', fontSize: 11 }
const AXIS_LINE = { stroke: 'rgba(0,0,0,0.15)' }

const AllDestinationsWindChart = ({
    weatherData,
    activeYear,
    activeDestinations,
    showAverageGustData,
    activeWindUnit,
    setActiveWindUnit,
    setShowAverageGustData,
    colours,
}: Props) => {
    const windDatapoint = showAverageGustData
        ? `${activeWindUnit}Gust`
        : `${activeWindUnit}Wind`

    const chartData = useMemo(
        () => prepareYearlyWindData(weatherData, activeYear, windDatapoint),
        [weatherData, activeYear, windDatapoint]
    )

    const CustomTooltip = ({ payload }: any) => {
        if (!payload?.length) return null
        const data = payload[0].payload
        const { month, ...restOfData } = data

        const activeLabels = activeDestinations.map((d) => d.label)
        const orderedData = Object.entries(restOfData)
            .map(([location, value]) => ({ location, value: value as number }))
            .filter((d) => activeLabels.includes(d.location))
            .sort((a, b) => b.value - a.value)

        return (
            <div className="min-w-[10rem] bg-white border border-black/10 p-3 shadow-xl">
                <p className="text-primary text-xs uppercase tracking-wide border-b border-black/10 pb-2 mb-2 flex items-center justify-between gap-x-3">
                    {month} <span className="text-secondary/50">{activeYear}</span>
                </p>
                <ul className="space-y-1.5">
                    {orderedData.map(({ location, value }) => (
                        <li
                            key={location}
                            className="flex items-center justify-between gap-x-4 text-xs"
                            style={{ color: colours[location] }}
                        >
                            <span className="truncate max-w-[8rem]">{location}</span>
                            <span className="font-medium tabular-nums">{value} {activeWindUnit}</span>
                        </li>
                    ))}
                </ul>
            </div>
        )
    }

    if (!chartData.length) return null

    return (
        <div className="bg-white border border-black/10 p-6 lg:p-8 space-y-6">
            {/* Header */}
            <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                    <h3 className="font-display text-secondary tracking-wide"
                        style={{ fontSize: 'clamp(1.4rem, 3vw, 2rem)' }}>
                        Wind Speed Averages
                    </h3>
                    <p className="text-secondary/50 text-xs mt-1">Monthly breakdown by spot · {activeYear}</p>
                </div>

                {/* Controls */}
                <div className="flex flex-wrap items-center gap-4 lg:gap-6">
                    {/* Wind/Gust toggle */}
                    <label className="inline-flex items-center cursor-pointer gap-2.5">
                        <span className={`text-xs uppercase tracking-wide ${!showAverageGustData ? 'text-secondary' : 'text-secondary/40'}`}>
                            Wind
                        </span>
                        <input
                            type="checkbox"
                            checked={showAverageGustData}
                            onChange={(e) => setShowAverageGustData(e.target.checked)}
                            className="sr-only peer"
                        />
                        <div className="relative w-10 h-5 bg-secondary/15 border border-secondary/20 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-secondary/20 after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary peer-checked:border-primary" />
                        <span className={`text-xs uppercase tracking-wide ${showAverageGustData ? 'text-secondary' : 'text-secondary/40'}`}>
                            Gust
                        </span>
                    </label>

                    {/* Unit radios */}
                    <div className="flex items-center gap-1 border border-secondary/15">
                        {unitOptions.map((unit) => {
                            const active = activeWindUnit === unit
                            return (
                                <button
                                    key={unit}
                                    onClick={() => setActiveWindUnit(unit)}
                                    className={`px-3 py-1.5 text-xs uppercase tracking-wide transition-colors duration-200 ${
                                        active
                                            ? 'bg-primary text-white'
                                            : 'text-secondary/50 hover:text-secondary'
                                    }`}
                                >
                                    {unit}
                                </button>
                            )
                        })}
                    </div>
                </div>
            </div>

            {/* Chart */}
            <div className="h-[22rem]">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={chartData} margin={{ top: 4, right: 8, left: 0, bottom: 50 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.08)" />
                        <XAxis
                            dataKey="month"
                            interval={0}
                            angle={-45}
                            textAnchor="end"
                            tick={AXIS_TICK}
                            axisLine={AXIS_LINE}
                            tickLine={AXIS_LINE}
                        />
                        <YAxis
                            tick={AXIS_TICK}
                            axisLine={AXIS_LINE}
                            tickLine={AXIS_LINE}
                            label={{
                                value: `Avg ${showAverageGustData ? 'Gust' : 'Wind'} (${activeWindUnit})`,
                                angle: -90,
                                position: 'insideLeft',
                                fill: 'rgba(0,0,0,0.5)',
                                fontSize: 11,
                            }}
                        />
                        <Tooltip content={<CustomTooltip />} />
                        {activeDestinations.map(({ label }) => (
                            <Line
                                key={label}
                                type="monotone"
                                dataKey={label}
                                stroke={colours[label]}
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 4, strokeWidth: 0 }}
                            />
                        ))}
                    </LineChart>
                </ResponsiveContainer>
            </div>

            {/* Disclaimer */}
            <p className="text-secondary/50 text-xs leading-relaxed border-t border-black/10 pt-4">
                <strong className="text-secondary">Note:</strong> Wind data calculated from historical records via the{' '}
                <a href="https://open-meteo.com/" target="_blank" rel="noreferrer noopener" className="underline underline-offset-2 hover:text-primary transition-colors">
                    Open-Meteo API
                </a>
                . Long-term averages — actual conditions may vary.
            </p>
        </div>
    )
}

export default AllDestinationsWindChart
