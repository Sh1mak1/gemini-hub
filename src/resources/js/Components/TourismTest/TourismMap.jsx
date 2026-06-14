import { useEffect, useState } from 'react';

export default function TourismMap(props) {
    const [MapView, setMapView] = useState(null);

    useEffect(() => {
        import('./TourismMapView').then((module) => {
            setMapView(() => module.default);
        });
    }, []);

    if (!MapView) {
        return (
            <div className="mx-auto flex h-[100vh] w-[80vw] max-w-full items-center justify-center rounded-3xl border border-[#e2d4bc] bg-[#faf4ea] text-sm text-[#8b7355]">
                地図を読み込み中…
            </div>
        );
    }

    return <MapView {...props} />;
}
