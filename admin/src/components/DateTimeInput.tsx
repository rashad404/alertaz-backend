import React, { useEffect, useState } from 'react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';

interface DateTimeInputProps {
  value?: string;
  onChange: (value: string) => void;
  className?: string;
  required?: boolean;
}

const DateTimeInput: React.FC<DateTimeInputProps> = ({
  value = '',
  onChange,
  className = '',
  required = false
}) => {
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);

  useEffect(() => {
    if (value) {
      // Convert ISO string to Date object
      // "2024-01-15T14:30" => Date object
      const date = new Date(value);
      if (!isNaN(date.getTime())) {
        setSelectedDate(date);
      }
    } else {
      // Set default to current date and time
      setSelectedDate(new Date());
    }
  }, [value]);

  const handleDateChange = (date: Date | null) => {
    setSelectedDate(date);

    if (date) {
      // Convert Date to ISO format string for storage
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');

      const isoString = `${year}-${month}-${day}T${hours}:${minutes}`;
      onChange(isoString);
    }
  };

  return (
    <div className="w-full">
      <DatePicker
        selected={selectedDate}
        onChange={handleDateChange}
        showTimeSelect
        timeFormat="HH:mm"
        timeIntervals={15}
        dateFormat="yyyy-MM-dd HH:mm"
        timeCaption="Time"
        className={`w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${className}`}
        required={required}
        placeholderText="Select date and time"
        showMonthDropdown
        showYearDropdown
        dropdownMode="select"
        calendarClassName="admin-datepicker"
        wrapperClassName="w-full"
      />
    </div>
  );
};

export default DateTimeInput;