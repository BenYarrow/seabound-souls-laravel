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
import { prepareYearlyTempData, WeatherDataset } from '@/Helpers/weatherDataHelpers'
import type { SelectOption } from './FilterDataset'

interface Props {
    weatherData: WeatherDataset
    activeYear: number
    activeDestinations: SelectOption[]
    colours: Record<string, string>
}

const AXIS_TICK = { fill: 'rgba(0,0,0,0.6)', fontSize: 11 }
const AXIS_LINE = { stroke: 'rgba(0,0,0,0.15)' }

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
                            <span className="font-medium tabular-nums">{value}°C</span>
                        </li>
                    ))}
                </ul>
            </div>
        )
    }

    if (!chartData.length || !activeDestinations.length) return null

    return (
        <div className="bg-white border border-black/10 p-6 lg:p-8 space-y-6">
            {/* Header */}
            <div>
                <h3 className="font-display text-secondary tracking-wide"
                    style={{ fontSize: 'clamp(1.4rem, 3vw, 2rem)' }}>
                    Temperature Trends
                </h3>
                <p className="text-secondary/50 text-xs mt-1">Annual averages by spot · {activeYear}</p>
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
                                value: 'Avg temp (°C)',
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
        </div>
    )
}

export default AllDestinationsTempChart
