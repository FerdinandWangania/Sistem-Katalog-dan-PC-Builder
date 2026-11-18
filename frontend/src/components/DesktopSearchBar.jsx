import { SearchBar } from './SearchBar.jsx';

// Varian search bar untuk desktop: lebih lebar dari default
// (w-80 di md, w-96 di lg), full-width di mobile.
// Dipakai di sub-header katalog.
export function DesktopSearchBar({ size = 'md', className = '', ...rest }) {
  return <SearchBar size={size} {...rest} className={`w-full md:w-80 lg:w-96 shrink-0 ${className}`} />;
}
