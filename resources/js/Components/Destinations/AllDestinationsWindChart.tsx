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
import { prepareYearlyWindData, WeatherDataset } from '@/helpers/weatherDataHelpers'
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
            <div className="min-w-[10rem] bg-white p-2 shadow-md">
                <p className="text-primary lg:text-lg border-b border-primary flex items-center justify-between gap-x-1 lg:gap-x-2">
                    {month}: <span>{activeYear}</span>
                </p>
                <ul className="w-full pt-2 lg:py-2 space-y-1 lg:space-y-2">
                    {orderedData.map(({ location, value }) => (
                        <li
                            key={location}
                            className="max-md:text-sm flex items-center justify-between gap-x-4"
                            style={{ color: colours[location] }}
                        >
                            {location}: <span>{`${value} (${activeWindUnit})`}</span>
                        </li>
                    ))}
                </ul>
            </div>
        )
    }

    if (!chartData.length) return null

    return (
        <div className="relative">
            <div className="absolute min-w-20 lg:min-w-24 flex items-center justify-center z-20 top-0 -translate-y-1/2 right-6 lg:right-8 bg-primary shadow-lg">
                <h3 className="text-white text-lg px-3 py-2">{activeYear}</h3>
            </div>
            <div className="w-full p-6 lg:p-8 bg-white space-y-8 shadow-lg">
                <div className="space-y-2 max-lg:pt-2">
                    <h2 className="text-lg lg:text-xl text-primary">
                        Wind Speed Averages: Monthly Breakdown by Spot
                    </h2>
                    <p className="text-sm text-gray-500">
                        See how wind speeds vary throughout the year across different locations.
                    </p>
                </div>

                <div className="pt-2 flex flex-col space-y-3 lg:space-y-0 lg:flex-row lg:items-center lg:justify-between">
                    <label className="inline-flex items-center cursor-pointer gap-x-2">
                        <span className={`text-xs md:text-sm ${!showAverageGustData ? 'text-black' : 'text-gray-400'}`}>
                            Avg Wind
                        </span>
                        <input
                            type="checkbox"
                            checked={showAverageGustData}
                            onChange={(e) => setShowAverageGustData(e.target.checked)}
                            className="sr-only peer"
                        />
                        <div className="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        <span className={`text-xs md:text-sm ${showAverageGustData ? 'text-black' : 'text-gray-400'}`}>
                            Avg Gust
                        </span>
                    </label>

                    <div className="flex items-center gap-x-4">
                        {unitOptions.map((unit) => {
                            const checked = activeWindUnit === unit
                            return (
                                <div key={unit} className="flex items-center gap-x-1">
                                    <input
                                        type="radio"
                                        id={`wind-unit-${unit}`}
                                        name="windUnit"
                                        value={unit}
                                        checked={checked}
                                        onChange={() => setActiveWindUnit(unit)}
                                        className="cursor-pointer"
                                    />
                                    <label
                                        htmlFor={`wind-unit-${unit}`}
                                        className={`text-xs md:text-sm cursor-pointer ${checked ? 'text-black' : 'text-gray-400'}`}
                                    >
                                        {unit.charAt(0).toUpperCase() + unit.slice(1)}
                                    </label>
                                </div>
                            )
                        })}
                    </div>
                </div>

                <div className="h-[25rem]">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={chartData} margin={{ top: 0, right: 5, left: 0, bottom: 50 }}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month" interval={0} angle={-45} textAnchor="end" />
                            <YAxis
                                label={{
                                    value: `Avg ${showAverageGustData ? 'Gust' : 'Wind'} (${activeWindUnit})`,
                                    angle: -90,
                                    position: 'insideLeft',
                                }}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            {activeDestinations.map(({ label }) => (
                                <Line
                                    key={label}
                                    type="monotone"
                                    dataKey={label}
                                    stroke={colours[label]}
                                    dot={false}
                                />
                            ))}
                        </LineChart>
                    </ResponsiveContainer>
                </div>

                <div className="prose prose-sm prose-p:!text-gray-600 prose-p:text-sm">
                    <p>
                        <strong>Please note:</strong> Wind data is calculated from 4 years of
                        historical records obtained from nearby official weather stations via the{' '}
                        <a href="https://open-meteo.com/" target="_blank" rel="noreferrer noopener">
                            Open-Meteo API
                        </a>
                        . These values are long-term averages and do not account for localised
                        thermal effects (such as sea/land breezes or valley winds). Actual,
                        real-time conditions, particularly in warmer seasons, may vary
                        significantly.
                    </p>
                </div>
            </div>
        </div>
    )
}

export default AllDestinationsWindChart
