export const chartColors = {
    wind: '#8884d8',
    gust: '#82ca9d',
    temp: '#ffc658',
}

const PALETTE = [
    '#8884d8',
    '#82ca9d',
    '#ffc658',
    '#ff7300',
    '#413ea0',
    '#00c49f',
    '#e6194b',
    '#3cb44b',
    '#4363d8',
    '#f58231',
    '#911eb4',
    '#42d4f4',
    '#f032e6',
    '#bfef45',
    '#fabed4',
    '#469990',
]

export const getSpotGuideColours = (titles: string[]): Record<string, string> => {
    const colours: Record<string, string> = {}
    titles.forEach((title, i) => {
        colours[title] = PALETTE[i % PALETTE.length]
    })
    return colours
}
