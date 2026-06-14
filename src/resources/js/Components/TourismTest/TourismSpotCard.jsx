import TourismSpotDescription from '@/Components/TourismTest/TourismSpotDescription';
import TourismSpotDistance from '@/Components/TourismTest/TourismSpotDistance';
import TourismSpotImage from '@/Components/TourismTest/TourismSpotImage';
import TourismSpotName from '@/Components/TourismTest/TourismSpotName';

export default function TourismSpotCard({ spot, locationName }) {
    return (
        <article className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-slate-950/40">
            <TourismSpotImage imageUrl={spot.image_url} name={spot.name} />
            <div className="space-y-1 p-4">
                <TourismSpotName name={spot.name} />
                <TourismSpotDistance
                    distanceText={spot.distance_text}
                    distanceKm={spot.distance_km}
                    locationName={locationName}
                />
                <TourismSpotDescription description={spot.description} />
            </div>
        </article>
    );
}
