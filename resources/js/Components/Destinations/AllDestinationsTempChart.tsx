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
import { prepareYearlyTempData, WeatherDataset } from '@/helpers/weatherDataHelpers'
import type { SelectOption } from './FilterDataset'

interface Props {
    weatherData: WeatherDataset
    activeYear: number
    activeDestinations: SelectOption[]
    colours: Record<string, string>
}

const AllDestinationsTempChart = ({
    weatherData,
    activeYear,
    activeDestinations,
    colours,
}: Props) => {
    const chartData = useMemo(
        () => prepareYearlyTempData(weatherData, activeYear),
        [weatherData, activeYear]
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
                <ul className="w-full py-1 lg:py-2 space-y-1 lg:space-y-2">
                    {orderedData.map(({ location, value }) => (
                        <li
                            key={location}
                            className="flex items-center justify-between gap-x-4"
                            style={{ color: colours[location] }}
                        >
                            {location}: <span>{`${value} (°C)`}</span>
                        </li>
                    ))}
                </ul>
            </div>
        )
    }

    if (!chartData.length || !activeDestinations.length) return null

    return (
        <div className="relative">
            <div className="absolute min-w-20 lg:min-w-24 flex items-center justify-center z-20 top-0 -translate-y-1/2 right-6 lg:right-8 bg-primary shadow-lg">
                <h3 className="text-white text-lg px-3 py-2">{activeYear}</h3>
            </div>
            <div className="w-full p-6 lg:p-8 bg-white space-y-8 shadow-lg">
                <div className="space-y-2 max-lg:pt-2">
                    <h2 className="text-lg lg:text-xl text-primary">
                        Annual Temperature Trends by Spot
                    </h2>
                    <p className="text-sm text-gray-500">
                        See how temperatures vary throughout the year across different locations.
                    </p>
                </div>

                <div className="h-[25rem]">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={chartData} margin={{ top: 0, right: 5, left: 0, bottom: 50 }}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month" interval={0} angle={-45} textAnchor="end" />
                            <YAxis
                                label={{
                                    value: 'Avg temp (°C)',
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
            </div>
        </div>
    )
}

export default AllDestinationsTempChart
